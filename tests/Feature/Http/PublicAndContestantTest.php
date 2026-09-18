<?php

namespace Tests\Feature\Http;

use App\Enums\EventStatus;
use App\Models\Vote;
use App\Services\LoginCodeService;
use App\Services\RegistrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class PublicAndContestantTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    /** @return array{0: \App\Models\Participant, 1: string} */
    private function contestantWithCode($event, int $cars = 1): array
    {
        $category = \App\Models\Category::query()->first() ?? $this->makeCategory(1, 'Camaro');
        $original = $event->status;
        $this->forceStatus($event, EventStatus::Setup);
        [$p, $code] = app(RegistrationService::class)->createContestant($event, ['name' => 'Casey Driver', 'email' => 'casey@example.test', 'phone' => '770-555-0101'], null);
        for ($i = 0; $i < $cars; $i++) {
            app(RegistrationService::class)->registerCar($p, ['category_id' => $category->id, 'description' => '1969 Camaro SS '.$i], null);
        }
        $this->forceStatus($event, $original);

        return [$p->fresh(['event', 'user']), $code];
    }

    public function test_gallery_is_public_and_hides_private_data(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [$p, $code] = $this->contestantWithCode($event);
        $car = $p->cars[0];

        $response = $this->get('/')->assertOk()->assertSee('1969 Camaro SS 0')->assertSee('Sign in to vote');
        $html = $response->getContent();
        foreach (['casey@example.test', '770-555-0101', 'Casey Driver', $code, app(LoginCodeService::class)->format($code)] as $secret) {
            $this->assertStringNotContainsString($secret, $html);
        }

        $this->get('/cars/'.$car->entry_number)->assertOk()->assertSee('Car #'.$car->entry_number)->assertDontSee('casey@example.test');
        $json = $this->getJson('/tallies')->assertOk()->json();
        $this->assertSame(0, $json['counts'][$car->entry_number] ?? 0);
        $this->assertStringNotContainsString('Casey', json_encode($json));
    }

    public function test_gallery_search_and_class_filter(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeCategory(1, 'Camaro');
        $b = $this->makeCategory(2, 'Mustang');
        $p = $this->makeContestant($event, 0, $a);
        $this->addCar($p, $a, 7);
        $mustang = $this->addCar($p, $b, 8);
        $mustang->update(['description' => '1966 Ford Mustang']);

        $this->get('/?q=Mustang')->assertSee('1966 Ford Mustang')->assertDontSee('Synthetic car');
        $this->get('/?q=%237')->assertSee('Synthetic car')->assertDontSee('1966 Ford Mustang');
        $this->get('/?class='.$a->id)->assertDontSee('1966 Ford Mustang');
    }

    public function test_code_login_normalizes_input_and_car_number_is_not_a_code(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [$p, $code] = $this->contestantWithCode($event);

        $this->post('/login', ['code' => (string) $p->cars[0]->entry_number])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/login', ['code' => strtolower(substr($code, 0, 5).' - '.substr($code, 5))])->assertRedirect(route('ballot.index'));
        $this->assertAuthenticatedAs($p->user);
    }

    public function test_code_login_is_throttled(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['code' => 'ZZZZZ-ZZZZZ']);
        }
        $this->post('/login', ['code' => 'ZZZZZ-ZZZZZ'])->assertSessionHasErrors(['code' => 'Too many tries. Wait a minute and try again.']);
    }

    public function test_code_login_grants_no_admin_access(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [, $code] = $this->contestantWithCode($event);
        $this->post('/login', ['code' => $code]);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/reports/reconciliation')->assertForbidden();
        $this->post('/admin/event/close', ['confirm_paper' => 1])->assertForbidden();
        $this->assertSame(EventStatus::VotingOpen, $event->fresh()->status);
    }

    public function test_guests_cannot_reach_ballot_or_admin(): void
    {
        $this->makeEvent(EventStatus::VotingOpen);
        $this->get('/ballot')->assertRedirect(route('login'));
        $this->post('/ballot', ['cars' => [1]])->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/paper')->assertRedirect(route('admin.login'));
    }

    public function test_full_online_ballot_review_and_confirm(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [$p, $code] = $this->contestantWithCode($event);
        $other = $this->makeContestant($event, 2);
        $this->post('/login', ['code' => $code]);

        $this->get('/ballot')->assertOk()->assertSee('Choose cars')->assertSee('Voter #'.$p->voter_number);

        $numbers = [$p->cars[0]->entry_number, $other->cars[0]->entry_number];
        $review = $this->post('/ballot/review', ['cars' => $numbers])->assertOk()->assertSee('Votes are final once confirmed');
        $this->assertSame(0, Vote::query()->count(), 'Review writes nothing');

        preg_match('/name="idempotency_key" value="([^"]+)"/', $review->getContent(), $m);
        $this->post('/ballot', ['cars' => $numbers, 'idempotency_key' => $m[1]])->assertRedirect(route('ballot.index'));
        $this->assertSame(2, Vote::query()->count());

        // Double submit of the same confirmed form does not add votes.
        $this->post('/ballot', ['cars' => $numbers, 'idempotency_key' => $m[1]])->assertRedirect(route('ballot.index'));
        $this->assertSame(2, Vote::query()->count());

        $this->get('/ballot')->assertSee('Saved')->assertSee('Voted');
        $this->get('/')->assertSee('My ballot')->assertDontSee('Contestant sign in');
    }

    public function test_duplicate_car_via_ballot_is_rejected_with_message(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [$p, $code] = $this->contestantWithCode($event);
        $this->post('/login', ['code' => $code]);
        $this->vote($p, [$p->cars[0]]);

        $this->post('/ballot', ['cars' => [$p->cars[0]->entry_number], 'idempotency_key' => (string) Str::uuid()])
            ->assertRedirect(route('ballot.index'))->assertSessionHasErrors();
        $this->assertSame(1, Vote::query()->count());
    }

    public function test_closed_voting_shows_status_not_controls(): void
    {
        $event = $this->makeEvent(EventStatus::VotingClosed);
        [, $code] = $this->contestantWithCode($event);
        $this->post('/login', ['code' => $code]);

        $this->get('/ballot')->assertSee('Voting is closed')->assertDontSee('Review votes');
        $this->get('/results')->assertSee('Voting has closed');
    }

    public function test_rotated_code_ends_existing_session(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        [$p, $code] = $this->contestantWithCode($event);
        $this->post('/login', ['code' => $code]);
        $this->get('/ballot')->assertOk();

        app(LoginCodeService::class)->rotate($p, $this->makeAdmin());

        $this->get('/ballot')->assertRedirect(route('login'));
        $this->assertGuest();
        $this->post('/login', ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_no_vote_edit_or_delete_endpoints_exist(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes());
        $voteWrites = $routes->filter(fn ($r) => str_contains($r->uri(), 'votes') && array_intersect($r->methods(), ['PUT', 'PATCH', 'DELETE']));
        $this->assertCount(0, $voteWrites);
        $ballotWrites = $routes->filter(fn ($r) => str_contains($r->uri(), 'ballot') && array_intersect($r->methods(), ['PUT', 'PATCH', 'DELETE']));
        $this->assertCount(0, $ballotWrites);
    }
}
