<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vote;

/**
 * Votes are admin-only reading, never editable. There is deliberately no update
 * or delete method: votes are immutable (see App\Models\Concerns\Immutable), and any
 * call to Gate::authorize('update'|'delete', Vote::class) falls back to a deny.
 */
class VotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Vote $vote): bool
    {
        return $user->isAdmin();
    }
}
