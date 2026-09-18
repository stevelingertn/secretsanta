<?php

namespace App\Models;

use App\Enums\VoteSource;
use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BallotSubmission extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['source' => VoteSource::class, 'vote_count' => 'integer'];
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
