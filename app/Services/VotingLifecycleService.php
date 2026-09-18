<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\Award;
use App\Models\Event;
use App\Models\User;
use App\Services\Results\AwardOutcome;
use App\Services\Results\ResultsCalculator;
use Illuminate\Support\Facades\DB;

/**
 * setup -> voting_open -> voting_closed -> finalized. No reopening.
 * Each transition takes the event row FOR UPDATE, so it serializes with vote submissions
 * (which hold a shared lock on the same row).
 */
class VotingLifecycleService
{
    public function __construct(private ResultsCalculator $calculator) {}

    public function open(Event $event, User $admin): Event
    {
        return $this->transition($event, EventStatus::Setup, function (Event $locked) use ($admin) {
            $locked->status = EventStatus::VotingOpen;
            $locked->voting_opened_at = now();
            $locked->save();
            AuditLog::record('voting.opened', $locked, $admin);
        }, 'Voting can only be opened from setup.');
    }

    /** "Voting Finished": closes online and paper voting, freezes registration, and fixes the tally. */
    public function close(Event $event, User $admin): Event
    {
        return $this->transition($event, EventStatus::VotingOpen, function (Event $locked) use ($admin) {
            $locked->status = EventStatus::VotingClosed;
            $locked->voting_closed_at = now();
            $locked->closed_tally_hash = $this->calculator->tallyHash($locked);
            $locked->save();
            AuditLog::record('voting.closed', $locked, $admin, null, [
                'contestant_votes' => array_sum($this->calculator->tally($locked)),
            ]);
        }, 'Voting is not open.');
    }

    /** Writes the immutable award snapshot. Refuses while any tie is unresolved. */
    public function finalize(Event $event, User $admin): Event
    {
        return $this->transition($event, EventStatus::VotingClosed, function (Event $locked) use ($admin) {
            if ($this->calculator->tallyHash($locked) !== $locked->closed_tally_hash) {
                throw new VotingException('The vote tally changed after voting closed. Results were not finalized.');
            }
            $results = $this->calculator->calculate($locked);
            if ($results['pending']) {
                throw new VotingException('Resolve every tie before finalizing results.');
            }

            $position = 0;
            foreach ([$results['overall'], ...$results['categories']] as $outcome) {
                $this->writeAward($locked, $outcome, $position++);
            }

            $locked->status = EventStatus::Finalized;
            $locked->finalized_at = now();
            $locked->save();
            AuditLog::record('results.finalized', $locked, $admin);
        }, 'Results can only be finalized after Voting Finished.');
    }

    private function writeAward(Event $event, AwardOutcome $outcome, int $position): void
    {
        $award = new Award;
        $award->event_id = $event->id;
        $award->scope_key = $outcome->scopeKey;
        $award->category_id = $outcome->category?->id;
        $award->position = $position;
        $award->outcome = $outcome->status === AwardOutcome::WINNER ? Award::OUTCOME_WINNER
            : ($outcome->status === AwardOutcome::NO_ELIGIBLE ? Award::OUTCOME_NO_ELIGIBLE : Award::OUTCOME_NO_VOTES);
        $award->car_id = $outcome->car?->id;
        $award->contestant_votes = $outcome->car ? $outcome->contestantVotes : 0;
        $award->system_votes = $outcome->systemVotes;
        $award->entry_number = $outcome->car?->entry_number;
        $award->vehicle = $outcome->car?->description;
        $award->owner_name = $outcome->car?->participant?->user?->name;
        $award->category_name = $outcome->category?->name ?? ($outcome->car?->category?->name);
        $award->explanation = $outcome->explanation;
        $award->save();
    }

    private function transition(Event $event, EventStatus $from, callable $apply, string $error): Event
    {
        return DB::transaction(function () use ($event, $from, $apply, $error) {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== $from) {
                throw new VotingException($error);
            }
            $apply($locked);
            $event->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        }, 3);
    }
}
