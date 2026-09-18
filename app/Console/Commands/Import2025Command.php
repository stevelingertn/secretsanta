<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\Import\EntrantImporter;
use App\Services\Import\EntrantWorkbookReader;
use App\Services\Import\ImportDryRunAborted;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * One-time import of the 2025 car show entrants into the "2025 Test Show" event.
 * Reads only the "Name Entry" sheet (cached formula values, real rows only) and
 * creates contestants + cars with zero votes. See PLAN.md section 2, item 14:
 * historical BallotVotes tallies are intentionally never imported.
 */
class Import2025Command extends Command
{
    public const EVENT_NAME = 'Secret Santa Car Show 2025 Test Show';

    protected $signature = 'app:import-2025 {--path=reference/Carshow 2025 Repair-4.xlsm} {--dry-run}';

    protected $description = 'Import 2025 car show entrants and cars from the Name Entry sheet.';

    public function handle(EntrantWorkbookReader $reader, EntrantImporter $importer): int
    {
        $path = $this->option('path');
        $path = str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path) ? $path : base_path($path);
        $dryRun = (bool) $this->option('dry-run');

        try {
            $sheet = $reader->read($path);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $event = Event::query()->where('name', self::EVENT_NAME)->first();
        if (! $event) {
            $event = new Event;
            $event->name = self::EVENT_NAME;
            $event->year = 2025;
            $event->location = 'Oakwood, Georgia';
            $event->is_test = true;
            $event->status = EventStatus::Setup;
            $event->is_active = Event::active() === null;
            $event->save();
        }

        if ($event->status !== EventStatus::Setup) {
            $this->error("Event \"{$event->name}\" is not in setup status; refusing to import.");

            return self::FAILURE;
        }

        $result = null;
        $failed = null;

        try {
            DB::transaction(function () use ($event, $sheet, $importer, $dryRun, &$result) {
                $result = $importer->import($event, $sheet['rows'], null);
                if ($dryRun) {
                    throw new ImportDryRunAborted;
                }
            });
        } catch (ImportDryRunAborted) {
            // expected: rolls back, summary still printed below
        } catch (Throwable $e) {
            $failed = $e;
        }

        if ($failed) {
            $this->error('Import failed: '.$failed->getMessage());

            return self::FAILURE;
        }

        $summary = [
            'Rows scanned' => $sheet['scanned'],
            'Real entrant rows' => count($sheet['rows']),
            'People created' => $result['people_created'],
            'Cars created' => $result['cars_created'],
            'Unchanged (already imported)' => $result['unchanged'],
            'Skipped (see report)' => $result['skipped'],
        ];

        $reportPath = $this->writeReport($event, $summary, $result['issues'], $dryRun);

        $this->info($dryRun ? 'Dry run (no changes committed):' : 'Import complete:');
        foreach ($summary as $label => $value) {
            $this->line("  {$label}: {$value}");
        }
        $this->line('BallotVotes history was intentionally not imported (no reliable voter attribution).');
        $this->line("Report: {$reportPath}");

        return self::SUCCESS;
    }

    /** @param array<string,int> $summary @param list<string> $issues */
    private function writeReport(Event $event, array $summary, array $issues, bool $dryRun): string
    {
        $lines = [
            'Secret Santa 2025 import report'.($dryRun ? ' (dry run)' : ''),
            'Event: '.$event->name,
            'Generated: '.now()->toDateTimeString(),
            '',
            'Counts:',
        ];
        foreach ($summary as $label => $value) {
            $lines[] = "  {$label}: {$value}";
        }
        $lines[] = '';
        $lines[] = 'BallotVotes history sheet was intentionally not imported (no reliable voter attribution).';
        $lines[] = '';
        $lines[] = 'Issues ('.count($issues).'):';
        foreach ($issues as $issue) {
            $lines[] = '  '.$issue;
        }

        $filename = 'imports/import-2025-'.now()->format('Ymd-His').'.txt';
        Storage::disk('local')->put($filename, implode("\n", $lines)."\n");

        return Storage::disk('local')->path($filename);
    }
}
