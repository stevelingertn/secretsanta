<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class ContestantAdminTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->makeEvent();

        $this->get(route('admin.contestants.index'))->assertRedirect(route('admin.login'));
    }

    public function test_contestant_user_gets_403(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event);

        $this->actingAs($participant->user)->get(route('admin.contestants.index'))->assertForbidden();
        $this->actingAs($participant->user)->get(route('admin.contestants.create'))->assertForbidden();
        $this->actingAs($participant->user)->get(route('admin.contestants.show', $participant))->assertForbidden();
        $this->actingAs($participant->user)->post(route('admin.contestants.store'), ['name' => 'X', 'category_id' => 1])->assertForbidden();
    }

    public function test_admin_registers_contestant_and_car_in_one_step(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');

        $response = $this->actingAs($admin)->post(route('admin.contestants.store'), [
            'name' => 'Brand New Person',
            'confirm_new' => 1,
            'category_id' => $category->id,
            'description' => '1969 Camaro',
        ]);

        $participant = Participant::query()->whereHas('user', fn ($q) => $q->where('name', 'Brand New Person'))->firstOrFail();
        $response->assertRedirect(route('admin.contestants.show', $participant));
        $this->assertCount(1, $participant->cars);
        $this->assertSame('1969 Camaro', $participant->cars[0]->description);
    }

    public function test_likely_match_panel_shown_for_duplicate_name(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $this->makeContestant($event, 1, $category, 'Jordan Smith');

        $response = $this->actingAs($admin)->post(route('admin.contestants.store'), [
            'name' => 'Jordan Smith',
            'category_id' => $category->id,
            'description' => '1970 Chevelle',
        ]);

        $response->assertOk();
        $response->assertSee('Possible matches');
        $response->assertSee('Jordan Smith');
        $this->assertCount(1, Participant::query()->where('event_id', $event->id)->get());
    }

    public function test_matches_panel_links_to_existing_participant_in_this_event(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $existing = $this->makeContestant($event, 1, $category, 'Jordan Smith');

        $response = $this->actingAs($admin)->post(route('admin.contestants.store'), [
            'name' => 'Jordan Smith',
            'category_id' => $category->id,
            'description' => '1970 Chevelle',
        ]);

        $response->assertOk();
        $response->assertSee('Already in this event');
        $response->assertSee(route('admin.contestants.show', $existing), false);
        $this->assertCount(1, Participant::query()->where('event_id', $event->id)->get());
    }

    public function test_allowance_override_and_reset_through_http(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $participant = $this->makeContestant($event, 1);

        $this->actingAs($admin)->put(route('admin.contestants.allowance', $participant), [
            'total' => 10,
            'reason' => 'Sponsor bonus',
        ])->assertRedirect(route('admin.contestants.show', $participant));

        $this->assertSame(10, $participant->fresh()->allowance_override);

        $this->actingAs($admin)->put(route('admin.contestants.allowance', $participant), [
            'reset' => 1,
            'reason' => 'No longer needed',
        ])->assertRedirect(route('admin.contestants.show', $participant));

        $this->assertNull($participant->fresh()->allowance_override);
    }

    public function test_allowance_cannot_be_set_below_votes_used(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $participant = $this->makeContestant($event, 2);
        $this->vote($participant, [$participant->cars[0]]);
        $this->vote($participant, [$participant->cars[1]]);

        $response = $this->actingAs($admin)->put(route('admin.contestants.allowance', $participant), [
            'total' => 1,
            'reason' => 'Try to shrink below used',
        ]);

        $response->assertSessionHasErrors('allowance');
        $this->assertNull($participant->fresh()->allowance_override);
    }

    public function test_rotate_code_changes_hash(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $participant = $this->makeContestant($event, 1);
        $oldHash = $participant->fresh()->login_code_hash;

        $this->actingAs($admin)->post(route('admin.contestants.rotate-code', $participant))
            ->assertRedirect(route('admin.contestants.show', $participant));

        $this->assertNotSame($oldHash, $participant->fresh()->login_code_hash);
    }

    public function test_login_code_never_appears_on_contestant_index(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $participant = $this->makeContestant($event, 1);
        $code = app(\App\Services\LoginCodeService::class)->reveal($participant);

        $this->actingAs($admin)->get(route('admin.contestants.index'))->assertDontSee($code);
        $this->actingAs($admin)->get(route('admin.contestants.show', $participant))->assertDontSee($code);
    }
}
