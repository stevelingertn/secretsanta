<?php

/**
 * Standalone worker for ConcurrentVotingTest. Runs one domain action in its own PHP process
 * against the testing database, starting at a shared wall-clock barrier to maximise overlap.
 *
 * Usage: php worker.php <json>
 *   {"action":"vote","participant":1,"cars":[3,4],"start":1726650000.5,"pause_ms":150}
 *   {"action":"close","event":1,"admin":2,"start":...}
 *   {"action":"override","participant":1,"total":2,"admin":2,"start":...}
 *
 * pause_ms sleeps right after the participant/event row lock is taken, holding the lock
 * so competing processes must queue. This lives here, not in application code.
 */

use App\Enums\VoteSource;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\AllowanceService;
use App\Services\VoteService;
use App\Services\VotingLifecycleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$job = json_decode($argv[1], true);
$pause = (int) ($job['pause_ms'] ?? 0);

if ($pause > 0) {
    DB::listen(function (QueryExecuted $q) use ($pause) {
        $sql = strtolower($q->sql);
        if (str_contains($sql, 'for update') || str_contains($sql, 'lock in share mode')) {
            usleep($pause * 1000);
        }
    });
}

// Warm up the connection before the barrier so start times line up.
DB::select('select 1');
while (microtime(true) < $job['start'] + ($job['delay_ms'] ?? 0) / 1000) {
    usleep(500);
}

$started = microtime(true);
try {
    switch ($job['action']) {
        case 'vote':
            $p = Participant::with('event')->findOrFail($job['participant']);
            $admin = isset($job['admin']) ? User::findOrFail($job['admin']) : null;
            app(VoteService::class)->submit($p, $job['cars'], $admin ? VoteSource::Manual : VoteSource::Online, $admin, $job['key'] ?? (string) Str::uuid());
            break;
        case 'close':
            app(VotingLifecycleService::class)->close(Event::findOrFail($job['event']), User::findOrFail($job['admin']));
            break;
        case 'override':
            app(AllowanceService::class)->setOverride(Participant::with('event')->findOrFail($job['participant']), $job['total'], 'concurrency test', User::findOrFail($job['admin']));
            break;
    }
    echo json_encode(['ok' => true, 'at' => $started, 'done' => microtime(true)]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'class' => get_class($e), 'at' => $started]);
}
