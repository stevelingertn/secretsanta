<?php

namespace Tests\Feature\Domain;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Services\AllowanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class AllowanceTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_one_car_grants_five_votes_and_two_cars_grant_ten(): void
    {
        $event = $this->makeEvent();
        $one = $this->makeContestant($event, 1);
        $two = $this->makeContestant($event, 2);

        $this->assertSame(5, $one->allowance());
        $this->assertSame(10, $two->allowance());
    }

    public function test_contestant_may_use_fewer_votes_than_allowed(): void
    {
        $event = $this->makeEvent();
        $voter = $this->makeContestant($event, 1);
        $other = $this->makeContestant($event, 2);
        $this->forceStatus($event, EventStatus::VotingOpen);

        $this->vote($voter, [$other->cars[0]]);

        $summary = app(AllowanceService::class)->summary($voter->fresh());
        $this->assertSame(['cars' => 1, 'default' => 5, 'override' => null, 'allowance' => 5, 'used' => 1, 'remaining' => 4], $summary);
    }

    public function test_new_car_raises_default_allowance_but_override_stays_fixed(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $p = $this->makeContestant($event, 1);
        $category = $p->cars[0]->category;

        $this->addCar($p, $category);
        $this->assertSame(10, $p->fresh()->allowance());

        app(AllowanceService::class)->setOverride($p, 7, 'Sponsor bonus', $admin);
        $this->addCar($p, $category);
        $this->assertSame(7, $p->fresh()->allowance());
        $this->assertSame(15, $p->fresh()->defaultAllowance());
    }

    public function test_override_and_reset_to_default(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $p = $this->makeContestant($event, 2);
        $service = app(AllowanceService::class);

        $service->setOverride($p, 3, 'Late check-in', $admin);
        $this->assertSame(3, $p->fresh()->allowance());

        $service->setOverride($p, 0, 'Withdrew', $admin);
        $this->assertSame(0, $p->fresh()->allowance());

        $service->setOverride($p, null, 'Reset', $admin);
        $this->assertNull($p->fresh()->allowance_override);
        $this->assertSame(10, $p->fresh()->allowance());
        $this->assertDatabaseHas('audit_logs', ['action' => 'allowance.reset', 'subject_id' => $p->id]);
    }

    public function test_cannot_lower_allowance_below_votes_used_and_votes_are_kept(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $targets = $this->makeContestant($event, 3);
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voter, $targets->cars->take(3)->all());

        try {
            app(AllowanceService::class)->setOverride($voter, 2, 'Oops', $admin);
            $this->fail('Expected rejection');
        } catch (VotingException $e) {
            $this->assertStringContainsString('already used 3 votes', $e->getMessage());
        }

        $this->assertSame(3, $voter->votes()->count());
        $this->assertNull($voter->fresh()->allowance_override);
        app(AllowanceService::class)->setOverride($voter, 3, 'Exactly used', $admin);
        $this->assertSame(0, $voter->fresh()->votesRemaining());
    }

    public function test_reset_is_blocked_when_default_is_below_votes_used(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $voter = $this->makeContestant($event, 1);
        $targets = $this->makeContestant($event, 7);
        $this->forceStatus($event, EventStatus::VotingOpen);
        app(AllowanceService::class)->setOverride($voter, 7, 'Extra', $admin);
        $this->vote($voter, $targets->cars->take(6)->all());

        $this->expectException(VotingException::class);
        app(AllowanceService::class)->setOverride($voter, null, 'Reset', $admin);
    }

    public function test_negative_override_and_missing_reason_are_rejected(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $p = $this->makeContestant($event, 1);

        $this->assertThrows(fn () => app(AllowanceService::class)->setOverride($p, -1, 'x', $admin), VotingException::class);
        $this->assertThrows(fn () => app(AllowanceService::class)->setOverride($p, 4, '  ', $admin), VotingException::class);
    }

    public function test_allowance_is_frozen_after_close(): void
    {
        $event = $this->makeEvent(EventStatus::VotingClosed);
        $admin = $this->makeAdmin();
        $p = $this->makeContestant($event, 1);

        $this->expectException(VotingException::class);
        app(AllowanceService::class)->setOverride($p, 9, 'Late', $admin);
    }
}
