<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\AwardTiebreak;
use App\Models\Event;
use App\Models\User;
use App\Services\Results\ResultsCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Resolves one tied award by choosing uniformly among the tied cars with random_int,
 * and records one award-scoped system vote. Decided once: later calls return the stored decision.
 */
class TieBreakService
{
    public function __construct(private ResultsCalculator $calculator) {}

    public function resolve(Event $event, string $scopeKey, User $admin): AwardTiebreak
    {
        try {
            return DB::transaction(function () use ($event, $scopeKey, $admin) {
                $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();

                $existing = AwardTiebreak::query()->where('event_id', $locked->id)->where('scope_key', $scopeKey)->first();
                if ($existing) {
                    return $existing;
                }
                if ($locked->status !== EventStatus::VotingClosed) {
                    throw new VotingException('Ties can only be resolved after Voting Finished and before results are final.');
                }

                $hash = $this->calculator->tallyHash($locked);
                if ($hash !== $locked->closed_tally_hash) {
                    throw new VotingException('The vote tally changed after voting closed. Stop and check the database before resolving ties.');
                }

                $outcome = $this->calculator->findOutcome($this->calculator->calculate($locked), $scopeKey);
                if (! $outcome || ! $outcome->canResolveTie()) {
                    throw new VotingException('That award does not have a tie waiting to be resolved.');
                }

                $candidates = collect($outcome->candidates)->sortBy(fn ($row) => $row['car']->entry_number)->values();
                $chosen = $candidates[random_int(0, $candidates->count() - 1)]['car'];

                $tiebreak = new AwardTiebreak;
                $tiebreak->event_id = $locked->id;
                $tiebreak->scope_key = $scopeKey;
                $tiebreak->category_id = $outcome->category?->id;
                $tiebreak->candidates = $candidates->map(fn ($row) => [
                    'car_id' => $row['car']->id,
                    'entry_number' => $row['car']->entry_number,
                    'votes' => $row['votes'],
                ])->all();
                $tiebreak->tied_votes = $outcome->contestantVotes;
                $tiebreak->chosen_car_id = $chosen->id;
                $tiebreak->system_votes = 1;
                $tiebreak->tally_hash = $hash;
                $tiebreak->resolved_by = $admin->id;
                $tiebreak->save();

                AuditLog::record('tiebreak.resolved', $locked, $admin, $tiebreak, [
                    'scope' => $scopeKey,
                    'candidates' => $candidates->map(fn ($row) => $row['car']->entry_number)->all(),
                    'chosen' => $chosen->entry_number,
                ]);

                return $tiebreak;
            });
        } catch (UniqueConstraintViolationException) {
            return AwardTiebreak::query()->where('event_id', $event->id)->where('scope_key', $scopeKey)->firstOrFail();
        }
    }
}
