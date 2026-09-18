<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A car class. IDs are preserved from carClasses.csv. */
class Category extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $fillable = ['id', 'name'];

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    /** Renaming would rewrite history once any event past setup references this class. */
    public function isLockedByHistory(): bool
    {
        return $this->cars()
            ->whereHas('event', fn ($q) => $q->where('status', '!=', EventStatus::Setup->value))
            ->exists();
    }
}
