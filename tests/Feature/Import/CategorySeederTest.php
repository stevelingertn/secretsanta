<?php

namespace Tests\Feature\Import;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'cats').'.csv';
        // UTF-8 BOM + CRLF, matching reference/carClasses.csv.
        file_put_contents($path, "\xEF\xBB\xBF".str_replace("\n", "\r\n", $body));

        return $path;
    }

    public function test_preserves_ids_and_reads_bom_csv(): void
    {
        $path = $this->writeCsv("classID,Class\n1,Bronco\n22,Chevelle\n");

        $counts = (new CategorySeeder)->seed($path);

        $this->assertSame(['created' => 2, 'updated' => 0, 'unchanged' => 0], $counts);
        $this->assertSame('Bronco', Category::find(1)->name);
        $this->assertSame('Chevelle', Category::find(22)->name);
    }

    public function test_idempotent_on_second_run(): void
    {
        $path = $this->writeCsv("classID,Class\n1,Bronco\n2,Camaro\n");
        $seeder = new CategorySeeder;

        $first = $seeder->seed($path);
        $second = $seeder->seed($path);

        $this->assertSame(['created' => 2, 'updated' => 0, 'unchanged' => 0], $first);
        $this->assertSame(['created' => 0, 'updated' => 0, 'unchanged' => 2], $second);
        $this->assertSame(2, Category::count());
    }

    public function test_renamed_row_updates_existing_id(): void
    {
        $seeder = new CategorySeeder;
        $seeder->seed($this->writeCsv("classID,Class\n1,Bronco\n"));

        $counts = $seeder->seed($this->writeCsv("classID,Class\n1,Bronco Classic\n"));

        $this->assertSame(['created' => 0, 'updated' => 1, 'unchanged' => 0], $counts);
        $this->assertSame('Bronco Classic', Category::find(1)->name);
    }

    public function test_rejects_non_positive_id_and_writes_nothing(): void
    {
        $path = $this->writeCsv("classID,Class\n1,Bronco\n0,Bad\n");

        $this->expectException(RuntimeException::class);
        try {
            (new CategorySeeder)->seed($path);
        } finally {
            $this->assertSame(0, Category::count());
        }
    }

    public function test_rejects_duplicate_name_in_file(): void
    {
        $path = $this->writeCsv("classID,Class\n1,Bronco\n2,Bronco\n");

        $this->expectException(RuntimeException::class);
        try {
            (new CategorySeeder)->seed($path);
        } finally {
            $this->assertSame(0, Category::count());
        }
    }

    public function test_rejects_name_already_used_by_a_different_id_in_the_database(): void
    {
        $seeder = new CategorySeeder;
        $seeder->seed($this->writeCsv("classID,Class\n1,Bronco\n"));

        $this->expectException(RuntimeException::class);
        try {
            $seeder->seed($this->writeCsv("classID,Class\n2,Bronco\n"));
        } finally {
            $this->assertSame(1, Category::count());
        }
    }

    public function test_rejects_best_overall(): void
    {
        $path = $this->writeCsv("classID,Class\n1,Best Overall\n");

        $this->expectException(RuntimeException::class);
        try {
            (new CategorySeeder)->seed($path);
        } finally {
            $this->assertSame(0, Category::count());
        }
    }
}
