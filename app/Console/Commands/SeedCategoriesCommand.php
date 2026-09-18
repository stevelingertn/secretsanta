<?php

namespace App\Console\Commands;

use Database\Seeders\CategorySeeder;
use Illuminate\Console\Command;
use RuntimeException;

/** Thin CLI wrapper around CategorySeeder so it can be re-run standalone with a custom CSV path. */
class SeedCategoriesCommand extends Command
{
    protected $signature = 'app:seed-categories {--path= : Path to carClasses.csv (defaults to reference/carClasses.csv)}';

    protected $description = 'Load car classes from carClasses.csv, preserving ids (idempotent).';

    public function handle(): int
    {
        try {
            $counts = (new CategorySeeder)->seed($this->option('path'));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Categories: {$counts['created']} created, {$counts['updated']} updated, {$counts['unchanged']} unchanged.");

        return self::SUCCESS;
    }
}
