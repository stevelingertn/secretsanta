<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'year', 'location', 'show_date', 'details', 'votes_per_car'];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'is_test' => 'boolean',
            'is_active' => 'boolean',
            'show_date' => 'date',
            'voting_opened_at' => 'datetime',
            'voting_closed_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->orderByDesc('id')->first();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function tiebreaks(): HasMany
    {
        return $this->hasMany(AwardTiebreak::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(Award::class)->orderBy('position');
    }

    public function isVotingOpen(): bool
    {
        return $this->status === EventStatus::VotingOpen;
    }

    /** Registration of new contestants and cars is allowed until voting closes. */
    public function allowsRegistration(): bool
    {
        return in_array($this->status, [EventStatus::Setup, EventStatus::VotingOpen], true);
    }

    /** Owner, class and deletion changes on existing cars are only safe before voting opens. */
    public function allowsStructuralCarChanges(): bool
    {
        return $this->status === EventStatus::Setup;
    }

    public function isClosedOrFinal(): bool
    {
        return in_array($this->status, [EventStatus::VotingClosed, EventStatus::Finalized], true);
    }

    public function displayName(): string
    {
        return $this->name;
    }
}
