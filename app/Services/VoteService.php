<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\BallotSubmission;
use App\Models\Car;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The only code path that writes contestant votes, for online ballots and admin paper entry.
 *
 * Locking (see PLAN.md): event row LOCK IN SHARE MODE, then participant row FOR UPDATE.
 * Closing voting takes the event row FOR UPDATE, so it waits for in-flight submissions
 * and every later submission sees the closed state. Unique indexes are the final guard.
 */
class VoteService
{
    /**
     * Parse "12, 7 31" style input into a list of tokens, keeping order and duplicates.
     *
     * @return list<string>
     */
    public function parseEntryList(string|array $input): array
    {
        if (is_array($input)) {
            return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $input), fn ($v) => $v !== ''));
        }

        return array_values(array_filter(preg_split('/[\s,;]+/', $input) ?: [], fn ($v) => $v !== ''));
    }

    /**
     * Resolve a ballot without writing anything. Used for review screens and paper-entry preview.
     *
     * @param  list<string|int>  $tokens
     * @return array{lines: list<array{input:string, status:string, message:?string, car:?Car}>, valid: bool, errors: list<string>, count:int, allowance:int, used:int, remaining:int, voted_car_ids: list<int>}
     */
    public function preview(Participant $participant, array $tokens): array
    {
        $event = $participant->event;
        $allowance = $participant->allowance();
        $votedCarIds = $participant->votes()->pluck('car_id')->all();
        $used = count($votedCarIds);
        $remaining = max(0, $allowance - $used);

        $numbers = [];
        foreach ($tokens as $token) {
            $clean = ltrim(trim((string) $token), '#');
            if (ctype_digit($clean)) {
                $numbers[] = (int) $clean;
            }
        }
        $cars = Car::query()->where('event_id', $event->id)->whereIn('entry_number', $numbers ?: [0])
            ->with('category')->get()->keyBy('entry_number');

        $lines = [];
        $errors = [];
        $seen = [];
        $okCount = 0;
        foreach ($tokens as $token) {
            $input = trim((string) $token);
            $clean = ltrim($input, '#');
            $line = ['input' => $input, 'status' => 'ok', 'message' => null, 'car' => null];

            if (! ctype_digit($clean) || (int) $clean < 1) {
                $line['status'] = 'invalid';
                $line['message'] = "\"{$input}\" is not a car number.";
            } elseif (! $cars->has((int) $clean)) {
                $line['status'] = 'not_found';
                $line['message'] = "Car #{$clean} is not registered in this show.";
            } else {
                $car = $cars->get((int) $clean);
                $line['car'] = $car;
                if (isset($seen[$car->id])) {
                    $line['status'] = 'duplicate';
                    $line['message'] = "Car #{$car->entry_number} is listed more than once. Each car can be chosen once.";
                } elseif (in_array($car->id, $votedCarIds, true)) {
                    $line['status'] = 'already_voted';
                    $line['message'] = "Car #{$car->entry_number} already has a vote from this contestant.";
                } else {
                    $okCount++;
                }
                $seen[$car->id] = true;
            }

            if ($line['message']) {
                $errors[] = $line['message'];
            }
            $lines[] = $line;
        }

        if ($tokens === []) {
            $errors[] = 'Choose at least one car.';
        }
        if (count($tokens) > $remaining) {
            $errors[] = 'This ballot lists '.count($tokens).' cars but only '.$remaining.' '.Str::plural('vote', $remaining).' remain.';
        }
        if ($event->status !== EventStatus::VotingOpen) {
            $errors[] = 'Voting is not open.';
        }

        return [
            'lines' => $lines,
            'valid' => $errors === [],
            'errors' => $errors,
            'count' => $okCount,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => $remaining,
            'voted_car_ids' => $votedCarIds,
        ];
    }

    /**
     * Commit a ballot atomically: every listed car gets one vote, or nothing is saved.
     * Retrying with the same idempotency key returns the original submission.
     *
     * @param  list<string|int>  $tokens  car entry numbers
     */
    public function submit(Participant $participant, array $tokens, VoteSource $source, ?User $admin, string $idempotencyKey): BallotSubmission
    {
        if ($source === VoteSource::Manual && ! $admin?->isAdmin()) {
            throw new VotingException('Only an admin can enter paper ballots.');
        }
        if (! Str::isUuid($idempotencyKey)) {
            throw new VotingException('This ballot form expired. Reload the page and try again.');
        }

        try {
            return DB::transaction(fn () => $this->submitLocked($participant, $tokens, $source, $admin, $idempotencyKey), 3);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent retry with the same key, or a duplicate vote that slipped past validation.
            $existing = BallotSubmission::query()->where('event_id', $participant->event_id)->where('idempotency_key', $idempotencyKey)->first();
            if ($existing && $existing->participant_id === $participant->id) {
                return $existing;
            }
            throw new VotingException('One of these cars already has a vote from this contestant. Nothing was saved.');
        }
    }

    private function submitLocked(Participant $participant, array $tokens, VoteSource $source, ?User $admin, string $key): BallotSubmission
    {
        $event = Event::query()->whereKey($participant->event_id)->sharedLock()->firstOrFail();
        $locked = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('event', $event);

        $existing = BallotSubmission::query()->where('event_id', $event->id)->where('idempotency_key', $key)->first();
        if ($existing) {
            if ($existing->participant_id !== $locked->id) {
                throw new VotingException('This ballot form belongs to another contestant. Reload the page.');
            }

            return $existing;
        }

        if ($event->status !== EventStatus::VotingOpen) {
            throw new VotingException($event->status === EventStatus::Setup ? 'Voting has not opened yet.' : 'Voting is closed. Nothing was saved.');
        }

        $preview = $this->preview($locked, $tokens);
        if (! $preview['valid']) {
            throw new VotingException('Nothing was saved. Fix the problems below and submit again.', $preview['errors']);
        }

        $submission = new BallotSubmission;
        $submission->event_id = $event->id;
        $submission->participant_id = $locked->id;
        $submission->source = $source;
        $submission->entered_by = $source === VoteSource::Manual ? $admin->id : null;
        $submission->idempotency_key = $key;
        $submission->vote_count = $preview['count'];
        $submission->save();

        $now = now();
        $rows = [];
        foreach ($preview['lines'] as $line) {
            $rows[] = [
                'event_id' => $event->id,
                'participant_id' => $locked->id,
                'user_id' => $locked->user_id,
                'car_id' => $line['car']->id,
                'ballot_submission_id' => $submission->id,
                'source' => $source->value,
                'entered_by' => $submission->entered_by,
                'created_at' => $now,
            ];
        }
        Vote::query()->insert($rows);

        if ($source === VoteSource::Manual) {
            AuditLog::record('ballot.manual_entry', $event, $admin, $submission, [
                'participant_id' => $locked->id,
                'votes' => count($rows),
            ]);
        }

        return $submission;
    }
}
