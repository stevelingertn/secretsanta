<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'address', 'city', 'state', 'zip'];

    protected $hidden = ['password', 'remember_token', 'email', 'phone', 'address', 'city', 'state', 'zip'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function isAdmin(): bool
    {
        return $this->is_admin === true;
    }

    public function participantFor(Event $event): ?Participant
    {
        return $this->participants()->where('event_id', $event->id)->first();
    }
}
