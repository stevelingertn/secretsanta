<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Car $car): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Car $car): bool
    {
        return $user->isAdmin();
    }

    /** Cars can only be removed while the event still allows structural changes (setup). */
    public function delete(User $user, Car $car): bool
    {
        return $user->isAdmin() && $car->event->allowsStructuralCarChanges();
    }
}
