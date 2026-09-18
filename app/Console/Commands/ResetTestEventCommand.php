<?php

namespace App\Console\Commands;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Local demo helper: wipes votes, ballots, tiebreaks, awards and audit rows for a TEST event
 * and returns it to setup, keeping its contestants and cars. Refuses outside APP_ENV=local
 * and for any event not flagged is_test. Not reachable from the web UI.
 */
class ResetTestEventCommand extends Command
{
    protected $signature = 'app:reset-test-event
        {event? : Event id (defaults to the active event)}
        {--confirm= : The exact event name, to skip the prompt}';

    protected $description = 'Local only: clear all voting data from a test event and return it to setup';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Refused: this command only runs with APP_ENV=local.');

            return self::FAILURE;
        }

        $event = $this->argument('event') ? Event::find($this->argument('event')) : Event::active();
        if (! $event || ! $event->is_test) {
            $this->error('Refused: pick an event marked as a test event.');

            return self::FAILURE;
        }

        $votes = DB::table('votes')->where('event_id', $event->id)->count();
        $this->warn("This deletes {$votes} votes, all ballot submissions, tiebreaks, awards and audit rows for \"{$event->name}\" and returns it to setup.");
        $typed = $this->option('confirm') ?? $this->ask('Type the event name exactly to confirm');
        if ($typed !== $event->name) {
            $this->info('Name did not match. Nothing changed.');

            return self::FAILURE;
        }

        // Query-builder deletes on purpose: the Eloquent models for these tables are immutable.
        DB::transaction(function () use ($event) {
            Event::query()->whereKey($event->id)->lockForUpdate()->first();
            foreach (['awards', 'award_tiebreaks', 'votes', 'ballot_submissions', 'audit_logs'] as $table) {
                DB::table($table)->where('event_id', $event->id)->delete();
            }
            DB::table('participants')->where('event_id', $event->id)->update(['allowance_override' => null]);
            DB::table('events')->where('id', $event->id)->update([
                'status' => EventStatus::Setup->value,
                'voting_opened_at' => null,
                'voting_closed_at' => null,
                'finalized_at' => null,
                'closed_tally_hash' => null,
            ]);
        });

        $this->info('Test event reset to setup. Contestants, cars and login codes were kept.');

        return self::SUCCESS;
    }
}
