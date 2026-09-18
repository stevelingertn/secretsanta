<?php

namespace Tests\Feature\Domain;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Exceptions\VotingException;
use App\Models\Vote;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class VoteServiceTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_self_vote_is_accepted(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $p = $this->makeContestant($event, 1);

        $this->vote($p, [$p->cars[0]]);

        $this->assertSame(1, $p->cars[0]->votes()->count());
    }

    public function test_cannot_vote_for_same_car_twice_online(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $car = $this->makeContestant($event, 1)->cars[0];
        $this->vote($voter, [$car]);

        $this->expectException(VotingException::class);
        $this->vote($voter, [$car]);
    }

    public function test_cannot_vote_for_same_car_across_online_and_manual(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $car = $this->makeContestant($event, 1)->cars[0];
        $this->vote($voter, [$car], VoteSource::Online);

        try {
            $this->vote($voter, [$car], VoteSource::Manual, $admin);
            $this->fail('Expected duplicate rejection');
        } catch (VotingException $e) {
            $this->assertStringContainsString("Car #{$car->entry_number} already has a vote", implode(' ', $e->errors));
        }
        $this->assertSame(1, Vote::query()->count());
    }

    public function test_manual_votes_record_admin_and_source(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $car = $this->makeContestant($event, 1)->cars[0];

        $submission = $this->vote($voter, [$car], VoteSource::Manual, $admin);

        $vote = Vote::query()->sole();
        $this->assertSame(VoteSource::Manual, $vote->source);
        $this->assertSame('Manually entered', $vote->source->label());
        $this->assertSame($admin->id, $vote->entered_by);
        $this->assertSame($submission->id, $vote->ballot_submission_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ballot.manual_entry', 'actor_id' => $admin->id]);
    }

    public function test_manual_entry_requires_admin(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);

        $this->expectException(VotingException::class);
        $this->vote($voter, [$voter->cars[0]], VoteSource::Manual, $voter->user);
    }

    public function test_same_idempotency_key_does_not_create_second_submission(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 2)->cars;
        $key = (string) Str::uuid();
        $service = app(VoteService::class);

        $first = $service->submit($voter, [$cars[0]->entry_number, $cars[1]->entry_number], VoteSource::Manual, $admin, $key);
        $second = $service->submit($voter->fresh('event'), [$cars[0]->entry_number, $cars[1]->entry_number], VoteSource::Manual, $admin, $key);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2, Vote::query()->count());
    }

    public function test_batch_is_atomic_when_one_car_is_invalid(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 2)->cars;

        try {
            $this->vote($voter, [$cars[0]->entry_number, 9999, $cars[1]->entry_number]);
            $this->fail('Expected rejection');
        } catch (VotingException $e) {
            $this->assertContains('Car #9999 is not registered in this show.', $e->errors);
        }
        $this->assertSame(0, Vote::query()->count());
        $this->assertSame(0, $voter->ballotSubmissions()->count());
    }

    public function test_duplicate_within_one_batch_fails_whole_batch(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $car = $this->makeContestant($event, 1)->cars[0];

        $this->assertThrows(fn () => $this->vote($voter, [$car, $car]), VotingException::class);
        $this->assertSame(0, Vote::query()->count());
    }

    public function test_over_quota_batch_fails(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 6)->cars;

        $this->assertThrows(fn () => $this->vote($voter, $cars->all()), VotingException::class);
        $this->assertSame(0, Vote::query()->count());

        $this->vote($voter, $cars->take(5)->all());
        $this->assertSame(0, $voter->fresh()->votesRemaining());
        $this->assertThrows(fn () => $this->vote($voter, [$cars[5]]), VotingException::class);
    }

    public function test_ten_votes_require_ten_distinct_cars(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 2);
        $cars = $this->makeContestant($event, 10)->cars;

        $this->vote($voter, $cars->all());

        $this->assertSame(10, $voter->votes()->distinct('car_id')->count('car_id'));
        $this->assertSame(0, $voter->fresh()->votesRemaining());
    }

    public function test_cross_event_car_is_rejected(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);

        $other = $this->makeEvent(EventStatus::VotingOpen);
        $foreign = $this->makeContestant($other, 1);
        $foreignCar = $this->addCar($foreign, $foreign->cars[0]->category, 500);

        try {
            $this->vote($voter, [500]);
            $this->fail('Expected rejection');
        } catch (VotingException $e) {
            $this->assertContains('Car #500 is not registered in this show.', $e->errors);
        }
        $this->assertSame(0, $foreignCar->votes()->count());
    }

    public function test_voting_rejected_unless_open(): void
    {
        foreach ([EventStatus::Setup, EventStatus::VotingClosed, EventStatus::Finalized] as $status) {
            $event = $this->makeEvent($status);
            $voter = $this->makeContestant($event, 1);
            $this->assertThrows(fn () => $this->vote($voter, [$voter->cars[0]]), VotingException::class);
        }
        $this->assertSame(0, Vote::query()->count());
    }

    public function test_votes_are_immutable(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $this->vote($voter, [$voter->cars[0]]);
        $vote = Vote::query()->sole();

        $this->assertThrows(fn () => $vote->delete(), LogicException::class);
        $vote->source = VoteSource::Manual;
        $this->assertThrows(fn () => $vote->save(), LogicException::class);
        $this->assertSame(1, Vote::query()->count());
    }

    public function test_preview_reports_each_problem_without_writing(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 2)->cars;
        $this->vote($voter, [$cars[0]]);

        $service = app(VoteService::class);
        $preview = $service->preview($voter->fresh('event'), $service->parseEntryList("{$cars[0]->entry_number}, {$cars[1]->entry_number} {$cars[1]->entry_number} abc"));

        $this->assertFalse($preview['valid']);
        $this->assertSame(['already_voted', 'ok', 'duplicate', 'invalid'], array_column($preview['lines'], 'status'));
        $this->assertSame(1, Vote::query()->count());
    }
}
