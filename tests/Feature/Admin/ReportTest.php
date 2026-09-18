<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Models\Event;
use App\Models\User;
use App\Services\ReportService;
use App\Services\Results\ResultsCalculator;
use App\Services\TieBreakService;
use App\Services\VotingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_totals_reconcile_across_reports(): void
    {
        $event = $this->makeEvent();
        $classA = $this->makeCategory(1, 'Camaro');
        $classB = $this->makeCategory(2, 'Mustang');
        $voterClass = $this->makeCategory(3, 'Voters');

        $ownerA = $this->makeContestant($event, 1, $classA);
        $ownerB = $this->makeContestant($event, 1, $classB);
        $carA = $ownerA->cars->first();
        $carB = $ownerB->cars->first();

        $voters = array_map(fn () => $this->makeContestant($event, 1, $voterClass), range(1, 3));
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);

        $this->vote($voters[0], [$carA]);
        $this->vote($voters[1], [$carA]);
        $this->vote($voters[2], [$carB], VoteSource::Manual, $admin);

        $service = app(ReportService::class);
        $byCar = $service->votesByCar($event);
        $byCategory = $service->votesByCategory($event);
        $reconciliation = $service->reconciliation($event);

        $sumByCar = $byCar->sum('contestant_votes');
        $sumByCategory = $byCategory->sum(fn ($group) => $group['total_votes']);

        $this->assertSame(3, $sumByCar);
        $this->assertSame(3, $sumByCategory);
        $this->assertSame($reconciliation['online_votes'] + $reconciliation['manual_votes'], $reconciliation['contestant_votes']);
        $this->assertSame(3, $reconciliation['contestant_votes']);
        $this->assertSame(2, $reconciliation['online_votes']);
        $this->assertSame(1, $reconciliation['manual_votes']);
    }

    public function test_effective_capacity_reflects_allowance_override(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $owner = $this->makeContestant($event, 1, $class);
        $owner->allowance_override = 12;
        $owner->save();

        $service = app(ReportService::class);
        $reconciliation = $service->reconciliation($event);

        // Without the override, one car would grant 5 votes (votes_per_car default).
        $this->assertNotEquals($event->votes_per_car, $reconciliation['effective_capacity']);
        $this->assertSame(12, $reconciliation['effective_capacity']);
        $this->assertSame(1, $reconciliation['contestants_with_override']);
    }

    public function test_system_tiebreak_vote_is_reported_separately_from_contestant_votes(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 0, $class);
        $carOne = $this->addCar($owner, $class);
        $carTwo = $this->addCar($owner, $class);
        $admin = $this->makeAdmin();

        $voters = array_map(fn () => $this->makeContestant($event, 1, $voterClass), range(1, 2));
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voters[0], [$carOne]);
        $this->vote($voters[1], [$carTwo]);

        app(VotingLifecycleService::class)->close($event, $admin);
        app(TieBreakService::class)->resolve($event->fresh(), ResultsCalculator::OVERALL, $admin);

        $service = app(ReportService::class);
        $byCar = $service->votesByCar($event->fresh());

        $winner = $byCar->first(fn ($row) => collect($row['tiebreak_votes'])->isNotEmpty());
        $this->assertNotNull($winner);
        $this->assertSame(1, $winner['contestant_votes']);
        $this->assertSame(1, $winner['tiebreak_votes'][0]['votes']);
        $this->assertSame('Best Overall', $winner['tiebreak_votes'][0]['scope_label']);

        $reconciliation = $service->reconciliation($event->fresh());
        $this->assertSame(2, $reconciliation['contestant_votes']);
        $this->assertCount(1, $reconciliation['tiebreak_votes']);
        $this->assertSame(1, $reconciliation['tiebreak_votes'][0]['votes']);
    }

    public function test_csv_download_has_headers_and_no_login_code_or_contact_fields(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $this->makeContestant($event, 1, $class);
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->get('/admin/reports/votes-by-car?format=csv');
        $response->assertOk();
        $content = $response->streamedContent();

        $lines = array_filter(explode("\n", trim($content)));
        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('Entry number', $lines[0]);
        $this->assertStringNotContainsString('login', strtolower($content));
        $this->assertStringNotContainsString('code', strtolower($content));
        $this->assertStringNotContainsString('phone', strtolower($content));
        $this->assertStringNotContainsString('email', strtolower($content));
        $this->assertStringNotContainsString('address', strtolower($content));
    }

    public function test_reports_and_votes_require_admin(): void
    {
        $event = $this->makeEvent();
        $contestant = User::factory()->create();

        $this->get('/admin/reports')->assertRedirect('/admin/login');
        $this->get('/admin/votes')->assertRedirect('/admin/login');

        $this->actingAs($contestant)->get('/admin/reports')->assertForbidden();
        $this->actingAs($contestant)->get('/admin/votes')->assertForbidden();
    }

    public function test_no_voter_names_appear_on_votes_or_reports_pages(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 1, $class, 'Owner Person');
        $voter = $this->makeContestant($event, 1, $voterClass, 'Secret Voter Name');
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, [$owner->cars->first()]);

        $response = $this->actingAs($admin)->get('/admin/votes');
        $response->assertOk();
        $response->assertDontSee('Secret Voter Name');

        $reportsResponse = $this->actingAs($admin)->get('/admin/reports/votes-by-car');
        $reportsResponse->assertOk();
        $reportsResponse->assertDontSee('Secret Voter Name');
    }

    public function test_awards_report_shows_provisional_then_final(): void
    {
        $event = $this->makeEvent();
        $class = $this->makeCategory(1, 'Camaro');
        $voterClass = $this->makeCategory(2, 'Voters');
        $owner = $this->makeContestant($event, 1, $class);
        $voter = $this->makeContestant($event, 1, $voterClass);
        $admin = $this->makeAdmin();
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, [$owner->cars->first()]);

        $provisional = $this->actingAs($admin)->get('/admin/reports/awards');
        $provisional->assertOk();
        $provisional->assertSee('Provisional');

        app(VotingLifecycleService::class)->close($event, $admin);
        app(VotingLifecycleService::class)->finalize($event->fresh(), $admin);

        $final = $this->actingAs($admin)->get('/admin/reports/awards');
        $final->assertOk();
        $final->assertSee('Final');
        $final->assertDontSee('Provisional');
    }
}
