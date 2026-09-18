<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads car classes from reference/carClasses.csv, preserving the CSV ids.
 * Production-safe: no users, no events, safe to re-run (upsert by id).
 * The file is BOM-prefixed, CRLF-terminated, header `classID,Class`.
 */
class CategorySeeder extends Seeder
{
    /** Overridable in tests without touching config. */
    public static ?string $path = null;

    /** @return array{created:int, updated:int, unchanged:int} */
    public function seed(?string $path = null): array
    {
        $path = $path ?? static::$path ?? config('carshow.categories_csv') ?? base_path('reference/carClasses.csv');

        if (! is_file($path)) {
            throw new RuntimeException("Category CSV not found at {$path}.");
        }

        $rows = $this->validate($this->parse($path));

        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];

        DB::transaction(function () use ($rows, &$counts) {
            foreach ($rows as $row) {
                $existing = Category::query()->find($row['id']);
                if (! $existing) {
                    Category::query()->create($row);
                    $counts['created']++;
                } elseif ($existing->name !== $row['name']) {
                    $existing->name = $row['name'];
                    $existing->save();
                    $counts['updated']++;
                } else {
                    $counts['unchanged']++;
                }
            }
        });

        return $counts;
    }

    public function run(): void
    {
        $this->seed();
    }

    /** @return list<array{id:int, name:string}> */
    private function parse(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Could not read category CSV at {$path}.");
        }

        // Strip UTF-8 BOM if present.
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $lines = preg_split('/\r\n|\r|\n/', $contents);

        $rows = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue; // header
            }
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $parts = str_getcsv($line);
            $rows[] = [
                'lineNumber' => $i + 1,
                'rawId' => $parts[0] ?? null,
                'rawName' => $parts[1] ?? null,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{lineNumber:int, rawId:mixed, rawName:mixed}>  $rawRows
     * @return list<array{id:int, name:string}>
     */
    private function validate(array $rawRows): array
    {
        $problems = [];
        $byId = [];
        $byName = [];
        $result = [];

        foreach ($rawRows as $row) {
            $rawId = trim((string) $row['rawId']);
            $name = trim((string) $row['rawName']);

            if (! ctype_digit($rawId) || (int) $rawId <= 0) {
                $problems[] = "Line {$row['lineNumber']}: invalid id \"{$row['rawId']}\".";

                continue;
            }
            $id = (int) $rawId;

            if ($name === '') {
                $problems[] = "Line {$row['lineNumber']}: empty class name.";

                continue;
            }

            if (strcasecmp($name, 'Best Overall') === 0) {
                $problems[] = "Line {$row['lineNumber']}: \"Best Overall\" is not a class.";

                continue;
            }

            if (isset($byId[$id])) {
                $problems[] = "Line {$row['lineNumber']}: duplicate id {$id} (also on line {$byId[$id]}).";

                continue;
            }

            $nameKey = mb_strtolower($name);
            if (isset($byName[$nameKey])) {
                $problems[] = "Line {$row['lineNumber']}: duplicate name \"{$name}\" (also on line {$byName[$nameKey]}).";

                continue;
            }

            $existing = Category::query()->where('name', $name)->first();
            if ($existing && (int) $existing->id !== $id) {
                $problems[] = "Line {$row['lineNumber']}: name \"{$name}\" already exists in the database under id {$existing->id}.";

                continue;
            }

            $byId[$id] = $row['lineNumber'];
            $byName[$nameKey] = $row['lineNumber'];
            $result[] = ['id' => $id, 'name' => $name];
        }

        if ($problems) {
            throw new RuntimeException("Category CSV rejected:\n".implode("\n", $problems));
        }

        return $result;
    }
}
