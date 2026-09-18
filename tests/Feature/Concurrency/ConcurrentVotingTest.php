<?php

namespace Tests\Feature\Concurrency;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Vote;
use App\Services\Results\ResultsCalculator;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

/**
 * Real multi-process races against MySQL (InnoDB). Data must be committed so worker
 * processes can see it, so this class manages its own truncation instead of RefreshDatabase.
 *
 * @group concurrency
 */
class ConcurrentVotingTest extends TestCase
{
    use BuildsShow;

    private const TABLES = ['audit_logs', 'awards', 'award_tiebreaks', 'votes', 'ballot_submissions', 'cars', 'participants', 'categories', 'events', 'users'];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Concurrency tests need MySQL/MariaDB.');
        }
        if (! Schema::hasTable('votes')) {
            Artisan::call('migrate', ['--force' => true]);
        }
        $this->truncate();
    }

    protected function tearDown(): void
    {
        $this->truncate();
        parent::tearDown();
    }

    private function truncate(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach (self::TABLES as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    /** @param list<array> $jobs @return list<array> */
    private function race(array $jobs, int $pauseMs = 150): array
    {
        $php = PHP_BINARY;
        $worker = base_path('tests/Concurrency/worker.php');
        $start = microtime(true) + 3.0;
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => config('database.connections.mysql.database')];

        $results = Process::pool(function (Pool $pool) use ($jobs, $php, $worker, $start, $pauseMs, $env) {
            foreach ($jobs as $job) {
                $pool->env($env)->timeout(60)->command([$php, $worker, json_encode($job + ['start' => $start, 'pause_ms' => $pauseMs])]);
            }
        })->start()->wait();

        $decoded = collect($results)->map(fn ($r) => json_decode($r->output(), true) ?? ['ok' => false, 'error' => 'worker crashed: '.$r->errorOutput()])->values()->all();
        // Evidence of overlap for HANDOFF.md: start/finish times and outcomes per worker.
        file_put_contents(storage_path('logs/concurrency-'.$this->name().'.json'), json_encode($decoded, JSON_PRETTY_PRINT));

        return $decoded;
    }

    public function test_parallel_single_car_ballots_cannot_exceed_allowance(): void
    {
        $event = $this->makeEvent(EventStatus::Setup);
        $voter = $this->makeContestant($event, 1);            // allowance 5
        $targets = $this->makeContestant($event, 10)->cars;
        $this->forceStatus($event, EventStatus::VotingOpen);

        $results = $this->race($targets->map(fn ($car) => ['action' => 'vote', 'participant' => $voter->id, 'cars' => [$car->entry_number]])->all());

        $this->assertSame(5, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(5, Vote::query()->where('participant_id', $voter->id)->count());
    }

    public function test_parallel_duplicate_votes_for_same_car_store_one(): void
    {
        $event = $this->makeEvent(EventStatus::Setup);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 2);
        $car = $this->makeContestant($event, 1)->cars[0];
        $this->forceStatus($event, EventStatus::VotingOpen);

        $jobs = [];
        for ($i = 0; $i < 6; $i++) {
            // Mix online and paper entry for the same contestant and car.
            $jobs[] = ['action' => 'vote', 'participant' => $voter->id, 'cars' => [$car->entry_number]] + ($i % 2 ? ['admin' => $admin->id] : []);
        }
        $results = $this->race($jobs);

        $this->assertSame(1, collect($results)->where('ok', true)->count(), json_encode($results));
        $this->assertSame(1, Vote::query()->count());
    }

    public function test_double_submit_with_same_idempotency_key_creates_one_ballot(): void
    {
        $event = $this->makeEvent(EventStatus::Setup);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $cars = $this->makeContestant($event, 3)->cars->pluck('entry_number')->all();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $key = (string) Str::uuid();

        $results = $this->race(array_fill(0, 4, ['action' => 'vote', 'participant' => $voter->id, 'cars' => $cars, 'admin' => $admin->id, 'key' => $key]));

        $this->assertTrue(collect($results)->every(fn ($r) => $r['ok']), json_encode($results));
        $this->assertSame(1, DB::table('ballot_submissions')->count());
        $this->assertSame(3, Vote::query()->count());
    }

    public function test_close_racing_with_votes_keeps_exactly_the_votes_committed_before_close(): void
    {
        $event = $this->makeEvent(EventStatus::Setup);
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Race');
        $voters = collect(range(1, 12))->map(fn () => $this->makeContestant($event, 1, $category));
        $target = $voters->first()->cars[0];
        $this->forceStatus($event, EventStatus::VotingOpen);

        // First six votes start together and hold their locks (150 ms pause per lock); close arrives
        // 60 ms later and must wait for them; the last six arrive after close is queued.
        $jobs = $voters->values()->map(fn ($v, $i) => ['action' => 'vote', 'participant' => $v->id, 'cars' => [$target->entry_number], 'delay_ms' => $i < 6 ? 0 : 150])->all();
        array_splice($jobs, 6, 0, [['action' => 'close', 'event' => $event->id, 'admin' => $admin->id, 'delay_ms' => 60]]);
        $results = $this->race($jobs, 150);

        $closed = Event::query()->findOrFail($event->id);
        $voteResults = collect($results)->reject(fn ($r, $i) => $i === 6);
        $this->assertTrue($results[6]['ok'], json_encode($results[6]));
        $this->assertSame(EventStatus::VotingClosed, $closed->status);

        $accepted = $voteResults->where('ok', true)->count();
        $rejected = $voteResults->where('ok', false);
        $this->assertGreaterThan(0, $accepted, 'In-flight votes committed before close. '.json_encode($results));
        $this->assertGreaterThan(0, $rejected->count(), 'Votes arriving after close were rejected. '.json_encode($results));
        $this->assertSame($accepted, Vote::query()->count(), 'Every accepted vote is stored; no rejected vote is stored');
        $this->assertTrue($rejected->every(fn ($r) => str_contains($r['error'], 'Voting is closed')), json_encode($rejected->values()));
        $this->assertSame($closed->closed_tally_hash, app(ResultsCalculator::class)->tallyHash($closed), 'Nothing was added after the close snapshot');
    }

    public function test_lowering_allowance_while_votes_arrive_never_leaves_votes_above_allowance(): void
    {
        $event = $this->makeEvent(EventStatus::Setup);
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $targets = $this->makeContestant($event, 5)->cars;
        $this->forceStatus($event, EventStatus::VotingOpen);

        $jobs = $targets->map(fn ($c) => ['action' => 'vote', 'participant' => $voter->id, 'cars' => [$c->entry_number]])->all();
        array_splice($jobs, 2, 0, [['action' => 'override', 'participant' => $voter->id, 'total' => 2, 'admin' => $admin->id]]);
        $this->race($jobs);

        $fresh = $voter->fresh('event');
        $this->assertLessThanOrEqual($fresh->allowance(), $fresh->votes()->count());
    }
}
