<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Production-safe seed: global car classes only. No users, no events, no test data.
     */
    public function run(): void
    {
        $this->call(CategorySeeder::class);
    }
}
