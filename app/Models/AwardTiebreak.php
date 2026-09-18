<?php

namespace App\Models;

use App\Models\Concerns\Immutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One system tiebreaker vote for one award scope. Never counted as a contestant vote. */
class AwardTiebreak extends Model
{
    use Immutable;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['candidates' => 'array', 'system_votes' => 'integer', 'tied_votes' => 'integer'];
    }

    public function chosenCar(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'chosen_car_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
