<?php

namespace App\Models;

use App\Enums\VoteSource;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contestant vote for one car. Written only by App\Services\VoteService. Final once saved.
 * The category is not stored: it is the target car's class, which is frozen once voting opens.
 */
class Vote extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['source' => VoteSource::class];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function ballotSubmission(): BelongsTo
    {
        return $this->belongsTo(BallotSubmission::class);
    }
}
