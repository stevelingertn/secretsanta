<?php

namespace App\Services;

use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AllowanceService
{
    /** @return array{cars:int, default:int, override:?int, allowance:int, used:int, remaining:int} */
    public function summary(Participant $participant): array
    {
        $cars = $participant->cars()->count();
        $used = $participant->votes()->count();
        $allowance = $participant->allowance($cars);

        return [
            'cars' => $cars,
            'default' => $participant->defaultAllowance($cars),
            'override' => $participant->allowance_override,
            'allowance' => $allowance,
            'used' => $used,
            'remaining' => max(0, $allowance - $used),
        ];
    }

    /** Set the total allowance, or null to reset to the default (5 x cars). Never below votes already used. */
    public function setOverride(Participant $participant, ?int $total, string $reason, User $admin): Participant
    {
        if ($total !== null && $total < 0) {
            throw new VotingException('The allowance cannot be negative.');
        }
        if (trim($reason) === '') {
            throw new VotingException('Enter a reason for the change.');
        }

        return DB::transaction(function () use ($participant, $total, $reason, $admin) {
            $event = Event::query()->whereKey($participant->event_id)->sharedLock()->firstOrFail();
            if (! $event->allowsRegistration()) {
                throw new VotingException('Allowances are frozen after voting closes.');
            }
            $locked = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('event', $event);

            $used = $locked->votes()->count();
            $newAllowance = $total ?? $locked->defaultAllowance();
            if ($newAllowance < $used) {
                throw new VotingException("This contestant has already used {$used} votes. The allowance cannot be set to {$newAllowance}.");
            }

            $old = $locked->allowance_override;
            $locked->allowance_override = $total;
            $locked->save();

            AuditLog::record($total === null ? 'allowance.reset' : 'allowance.override', $event, $admin, $locked, [
                'old_override' => $old,
                'new_override' => $total,
                'effective' => $newAllowance,
                'votes_used' => $used,
                'reason' => mb_substr(trim($reason), 0, 250),
            ]);

            return $locked;
        }, 3);
    }
}
