<?php

namespace Tests\Feature\Http;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Models\AwardTiebreak;
use App\Models\Vote;
use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class AdminFlowTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_admin_password_login_and_contestant_code_cannot_sign_in_as_admin(): void
    {
        $this->makeEvent();
        $admin = $this->makeAdmin();

        $this->post('/admin/login', ['username' => $admin->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/admin/login', ['username' => $admin->username, 'password' => 'password'])->assertRedirect(route('admin.dashboard'));
        $this->get('/admin')->assertOk()->assertSee('Cars entered');
    }

    public function test_open_and_close_require_confirmation(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/event/open')->assertSessionHasErrors('confirm');
        $this->actingAs($admin)->post('/admin/event/open', ['confirm' => 1])->assertRedirect();
        $this->assertSame(EventStatus::VotingOpen, $event->fresh()->status);

        $this->actingAs($admin)->post('/admin/event/close')->assertSessionHasErrors('confirm_paper');
        $this->assertSame(EventStatus::VotingOpen, $event->fresh()->status);
        $this->actingAs($admin)->post('/admin/event/close', ['confirm_paper' => 1])->assertRedirect(route('admin.results.show'));
        $this->assertSame(EventStatus::VotingClosed, $event->fresh()->status);
    }

    public function test_paper_ballot_lookup_preview_and_confirm_is_idempotent(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 3)->cars;

        $this->actingAs($admin)->post('/admin/paper/find', ['q' => (string) $voter->voter_number])
            ->assertRedirect(route('admin.paper.create', $voter));
        $code = app(LoginCodeService::class)->reveal($voter);
        $this->actingAs($admin)->post('/admin/paper/find', ['q' => $code])->assertRedirect(route('admin.paper.create', $voter));

        $input = $cars[0]->entry_number.', '.$cars[1]->entry_number;
        $preview = $this->actingAs($admin)->post("/admin/paper/{$voter->id}/preview", ['car_numbers' => $input])
            ->assertOk()->assertSee('Save 2 votes as Manually entered');
        $this->assertSame(0, Vote::query()->count());

        preg_match('/name="idempotency_key" value="([^"]+)"/', $preview->getContent(), $m);
        $payload = ['tokens' => [(string) $cars[0]->entry_number, (string) $cars[1]->entry_number], 'idempotency_key' => $m[1]];
        $this->actingAs($admin)->post("/admin/paper/{$voter->id}", $payload)->assertRedirect(route('admin.paper.index'));
        $this->actingAs($admin)->post("/admin/paper/{$voter->id}", $payload)->assertRedirect(route('admin.paper.index'));

        $this->assertSame(2, Vote::query()->where('source', VoteSource::Manual->value)->where('entered_by', $admin->id)->count());
    }

    public function test_paper_preview_flags_overlap_with_online_votes(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 2)->cars;
        $this->vote($voter, [$cars[0]], VoteSource::Online);

        $this->actingAs($admin)->post("/admin/paper/{$voter->id}/preview", ['car_numbers' => $cars[0]->entry_number.' '.$cars[1]->entry_number.' 999'])
            ->assertOk()
            ->assertSee('already has a vote from this contestant')
            ->assertSee('Car #999 is not registered')
            ->assertSee('This ballot cannot be saved as entered')
            ->assertDontSee('as Manually entered');
    }

    public function test_paper_entry_for_participant_in_other_event_is_404(): void
    {
        $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $old = $this->makeEvent(EventStatus::VotingOpen);
        $old->is_active = false;
        $old->save();
        $foreign = $this->makeContestant($old, 1);

        $this->actingAs($admin)->get("/admin/paper/{$foreign->id}")->assertNotFound();
    }

    public function test_results_tiebreak_button_and_finalize_via_http(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $cat = $this->makeCategory(1, 'A');
        $a = $this->makeContestant($event, 1, $cat);
        $b = $this->makeContestant($event, 1, $cat);
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($a, [$a->cars[0], $b->cars[0]]);
        $this->actingAs($admin)->post('/admin/event/close', ['confirm_paper' => 1]);

        $this->actingAs($admin)->get('/admin/results')->assertOk()->assertSee('Provisional: ties pending')->assertSee('Break this tie');
        $this->actingAs($admin)->get('/admin/results/print')->assertOk()->assertSee('Provisional');
        $this->actingAs($admin)->post('/admin/results/finalize', ['confirm' => 1])->assertSessionHasErrors();

        $this->actingAs($admin)->post('/admin/results/tiebreak', ['scope' => 'overall'])->assertRedirect(route('admin.results.show'));
        $this->actingAs($admin)->post('/admin/results/tiebreak', ['scope' => 'overall']);
        $this->assertSame(1, AwardTiebreak::query()->count());

        $this->actingAs($admin)->post('/admin/results/finalize', ['confirm' => 1])->assertRedirect(route('admin.results.show'));
        $this->assertSame(EventStatus::Finalized, $event->fresh()->status);
        $this->actingAs($admin)->get('/admin/results/print')->assertSee('Final')->assertSee('tiebreaker (system) vote');
        $this->get('/results')->assertSee('Best Overall')->assertSee('won a tiebreaker');
    }
}
