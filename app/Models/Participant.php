<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's participation in one event: voter number, login code, allowance override.
 * One participant per person per event, regardless of how many cars they enter.
 */
class Participant extends Model
{
    use HasFactory;

    protected $hidden = ['login_code_encrypted', 'login_code_hash'];

    protected function casts(): array
    {
        return [
            'allowance_override' => 'integer',
            'session_version' => 'integer',
            'voter_number' => 'integer',
            'code_rotated_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class)->orderBy('entry_number');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function ballotSubmissions(): HasMany
    {
        return $this->hasMany(BallotSubmission::class);
    }

    public function defaultAllowance(?int $carCount = null): int
    {
        $carCount ??= $this->cars()->count();

        return $carCount * (int) $this->event->votes_per_car;
    }

    public function allowance(?int $carCount = null): int
    {
        return $this->allowance_override ?? $this->defaultAllowance($carCount);
    }

    public function votesUsed(): int
    {
        return $this->votes()->count();
    }

    public function votesRemaining(): int
    {
        return max(0, $this->allowance() - $this->votesUsed());
    }
}
