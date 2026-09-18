<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class VoteListTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_index_lists_votes_with_counts_and_no_voter_column(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 1, $class);
        $voter = $this->makeContestant($event, 1, $voterClass, 'Secret Voter Name');
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, [$owner->cars->first()]);

        $response = $this->actingAs($admin)->get('/admin/votes');
        $response->assertOk();
        $response->assertSee('Online: 1', false);
        $response->assertSee('Votes are final and cannot be edited or removed.');
        $response->assertDontSee('Secret Voter Name');
    }

    public function test_manual_vote_shows_entered_by_admin(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 1, $class);
        $voter = $this->makeContestant($event, 1, $voterClass);
        $admin = $this->makeAdmin();
        $admin->name = 'Poll Admin';
        $admin->save();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, [$owner->cars->first()], VoteSource::Manual, $admin);

        $response = $this->actingAs($admin)->get('/admin/votes');
        $response->assertOk();
        $response->assertSee('Manually entered');
        $response->assertSee('Poll Admin');
    }

    public function test_filters_by_source_and_car_number(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 2, $class);
        [$carOne, $carTwo] = $owner->cars->all();
        $voterA = $this->makeContestant($event, 1, $voterClass);
        $voterB = $this->makeContestant($event, 1, $voterClass);
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voterA, [$carOne]);
        $this->vote($voterB, [$carTwo], VoteSource::Manual, $admin);

        $bySource = $this->actingAs($admin)->get('/admin/votes?source=manual');
        $bySource->assertOk();
        $bySource->assertSee($carTwo->description);
        $bySource->assertDontSee($carOne->description);

        $byCar = $this->actingAs($admin)->get('/admin/votes?car='.$carOne->entry_number);
        $byCar->assertOk();
        $byCar->assertSee($carOne->description);
        $byCar->assertDontSee($carTwo->description);
    }

    public function test_show_requires_vote_belongs_to_active_event(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 1, $class);
        $voter = $this->makeContestant($event, 1, $voterClass);
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, [$owner->cars->first()]);
        $vote = \App\Models\Vote::query()->where('event_id', $event->id)->firstOrFail();

        $this->actingAs($admin)->get('/admin/votes/'.$vote->id)->assertOk();

        // A different event is now active: the vote no longer belongs to the active event.
        $otherEvent = Event::factory()->create(['is_active' => true, 'status' => EventStatus::Setup]);
        $event->refresh();
        $event->is_active = false;
        $event->save();

        $this->actingAs($admin)->get('/admin/votes/'.$vote->id)->assertNotFound();
    }

    public function test_votes_pages_redirect_guests_and_forbid_non_admin(): void
    {
        $event = $this->makeEvent();
        $contestant = User::factory()->create();

        $this->get('/admin/votes')->assertRedirect('/admin/login');
        $this->actingAs($contestant)->get('/admin/votes')->assertForbidden();
    }
}
