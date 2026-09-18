<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Synthetic people for tests only. Never used for real contestant data.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }

    public function admin(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->username = 'admin'.fake()->unique()->numberBetween(1, 999999);
            $user->password = Hash::make('password');
            $user->is_admin = true;
        });
    }
}
