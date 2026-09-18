<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Finalized award snapshot. Written once at finalize; printing reads these rows. */
class Award extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    public const OUTCOME_WINNER = 'winner';
    public const OUTCOME_NO_VOTES = 'no_votes';
    public const OUTCOME_NO_ELIGIBLE = 'no_eligible';

    protected function casts(): array
    {
        return ['contestant_votes' => 'integer', 'system_votes' => 'integer', 'entry_number' => 'integer'];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
