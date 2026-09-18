<?php

namespace Tests\Feature\Admin;

use App\Services\LoginCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class BallotPrintTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event);

        $this->get(route('admin.contestants.ballot', $participant))->assertRedirect(route('admin.login'));
    }

    public function test_contestant_user_gets_403(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event);

        $this->actingAs($participant->user)->get(route('admin.contestants.ballot', $participant))->assertForbidden();
    }

    public function test_ballot_print_shows_the_login_code(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $participant = $this->makeContestant($event, 1);
        $code = app(LoginCodeService::class)->reveal($participant);

        $this->actingAs($admin)->get(route('admin.contestants.ballot', $participant))
            ->assertOk()
            ->assertSee($code);
    }

    public function test_all_ballots_page_shows_every_contestant(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $a = $this->makeContestant($event, 1, null, 'Alice Racer');
        $b = $this->makeContestant($event, 1, null, 'Bob Driver');

        $response = $this->actingAs($admin)->get(route('admin.contestants.ballots'))->assertOk();
        $response->assertSee('Alice Racer');
        $response->assertSee('Bob Driver');
        $response->assertSee(app(LoginCodeService::class)->reveal($a->fresh()));
        $response->assertSee(app(LoginCodeService::class)->reveal($b->fresh()));
    }

    public function test_login_code_never_appears_on_public_gallery(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event, 1);
        $code = app(LoginCodeService::class)->reveal($participant);

        $this->get(route('gallery'))->assertOk()->assertDontSee($code);
    }
}
