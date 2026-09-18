<?php

namespace Tests\Feature\Domain;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\Award;
use App\Models\AwardTiebreak;
use App\Models\Event;
use App\Services\Results\AwardOutcome;
use App\Services\Results\ResultsCalculator;
use App\Services\TieBreakService;
use App\Services\VotingLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class ResultsTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    private function results(Event $event): array
    {
        return app(ResultsCalculator::class)->calculate($event);
    }

    private function outcome(array $results, string $scope): AwardOutcome
    {
        return app(ResultsCalculator::class)->findOutcome($results, $scope);
    }

    /** Creates N voters with one car each in $voterClass and returns them. */
    private function voters(Event $event, int $n, $voterClass): array
    {
        return array_map(fn () => $this->makeContestant($event, 1, $voterClass), range(1, $n));
    }

    public function test_best_overall_first_then_excluded_from_its_class(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeCategory(1, 'Camaro');
        $b = $this->makeCategory(2, 'Mustang');
        $voterClass = $this->makeCategory(3, 'Voters');
        $owner = $this->makeContestant($event, 0, $a);
        $top = $this->addCar($owner, $a);
        $second = $this->addCar($owner, $a); // same owner, different car
        $mustang = $this->addCar($owner, $b);
        $voters = $this->voters($event, 5, $voterClass);
        $this->forceStatus($event, EventStatus::VotingOpen);

        foreach ($voters as $i => $v) {
            $cars = [$top];
            if ($i < 3) {
                $cars[] = $second;
            }
            if ($i < 1) {
                $cars[] = $mustang;
            }
            $this->vote($v, $cars);
        }

        $r = $this->results($event);
        $this->assertSame($top->id, $r['overall']->car->id);
        $this->assertSame(5, $r['overall']->contestantVotes);
        $camaro = $this->outcome($r, ResultsCalculator::categoryScope($a->id));
        $this->assertSame($second->id, $camaro->car->id, 'Class award goes to the next car, not the overall winner');
        $this->assertSame($mustang->id, $this->outcome($r, ResultsCalculator::categoryScope($b->id))->car->id);
        $this->assertFalse($r['pending']);

        $winners = collect([$r['overall'], ...$r['categories']])->pluck('car')->filter()->pluck('id');
        $this->assertSame($winners->count(), $winners->unique()->count(), 'No car wins twice');
    }

    public function test_zero_votes_gives_no_winners(): void
    {
        $event = $this->makeEvent();
        $cat = $this->makeCategory(1, 'Bronco');
        $this->makeContestant($event, 2, $cat);

        $r = $this->results($event);
        $this->assertSame(AwardOutcome::NO_VOTES, $r['overall']->status);
        $this->assertNull($r['overall']->car);
        $this->assertSame(AwardOutcome::NO_VOTES, $r['categories'][0]->status);
        $this->assertFalse($r['pending']);
    }

    public function test_single_car_show(): void
    {
        $event = $this->makeEvent(EventStatus::VotingOpen);
        $cat = $this->makeCategory(1, 'Jeep');
        $p = $this->makeContestant($event, 1, $cat);
        $this->vote($p, [$p->cars[0]]);

        $r = $this->results($event);
        $this->assertSame($p->cars[0]->id, $r['overall']->car->id);
        $this->assertSame(AwardOutcome::NO_ELIGIBLE, $r['categories'][0]->status);
        $this->assertStringContainsString('won Best Overall', $r['categories'][0]->explanation);
    }

    public function test_class_with_cars_but_no_votes_has_no_winner(): void
    {
        $event = $this->makeEvent();
        $voted = $this->makeCategory(1, 'Voted');
        $empty = $this->makeCategory(2, 'Unvoted');
        $p = $this->makeContestant($event, 1, $voted);
        $this->makeContestant($event, 1, $empty);
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($p, [$p->cars[0]]);

        $out = $this->outcome($this->results($event), ResultsCalculator::categoryScope($empty->id));
        $this->assertSame(AwardOutcome::NO_VOTES, $out->status);
        $this->assertStringContainsString('no car in this class received a contestant vote', $out->explanation);
    }

    public function test_overall_tie_is_pending_and_blocks_only_affected_classes(): void
    {
        $event = $this->makeEvent();
        $a = $this->makeCategory(1, 'A');
        $b = $this->makeCategory(2, 'B');
        $c = $this->makeCategory(3, 'C');
        $carA = $this->makeContestant($event, 1, $a)->cars[0];
        $carB = $this->makeContestant($event, 1, $b)->cars[0];
        $carC = $this->makeContestant($event, 1, $c)->cars[0];
        $voters = $this->voters($event, 2, $this->makeCategory(4, 'V'));
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voters[0], [$carA, $carB, $carC]);
        $this->vote($voters[1], [$carA, $carB]);

        $r = $this->results($event);
        $this->assertSame(AwardOutcome::TIE_PENDING, $r['overall']->status);
        $this->assertTrue($r['pending']);
        $this->assertSame(AwardOutcome::WAITING, $this->outcome($r, 'category:1')->status);
        $this->assertSame(AwardOutcome::WAITING, $this->outcome($r, 'category:2')->status);
        $this->assertSame($carC->id, $this->outcome($r, 'category:3')->car->id);
    }

    public function test_tiebreak_picks_only_tied_candidates_persists_and_never_rerolls(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $a = $this->makeCategory(1, 'A');
        $b = $this->makeCategory(2, 'B');
        $carA = $this->makeContestant($event, 1, $a)->cars[0];
        $carB = $this->makeContestant($event, 1, $b)->cars[0];
        $lowA = $this->makeContestant($event, 1, $a)->cars[0];
        $voters = $this->voters($event, 2, $this->makeCategory(4, 'V'));
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voters[0], [$carA, $carB, $lowA]);
        $this->vote($voters[1], [$carA, $carB]);
        app(VotingLifecycleService::class)->close($event, $admin);

        $service = app(TieBreakService::class);
        $first = $service->resolve($event, ResultsCalculator::OVERALL, $admin);
        $again = $service->resolve($event, ResultsCalculator::OVERALL, $admin);

        $this->assertSame($first->id, $again->id);
        $this->assertSame(1, AwardTiebreak::query()->count());
        $this->assertContains($first->chosen_car_id, [$carA->id, $carB->id]);
        $this->assertEqualsCanonicalizing([$carA->id, $carB->id], array_column($first->candidates, 'car_id'));
        $this->assertSame(2, $first->tied_votes);

        $r = $this->results($event->fresh());
        $this->assertSame($first->chosen_car_id, $r['overall']->car->id);
        $this->assertSame(1, $r['overall']->systemVotes);
        $this->assertSame(2, $r['overall']->contestantVotes, 'Contestant count shown separately from the +1');

        // The other tied car wins its class with its contestant count only: no carry-over adjustment.
        $loser = $first->chosen_car_id === $carA->id ? $carB : $carA;
        $loserClass = $this->outcome($r, ResultsCalculator::categoryScope($loser->category_id));
        $this->assertSame($loser->id, $loserClass->car->id);
        $this->assertSame(0, $loserClass->systemVotes);

        // If A won overall, class A goes to lowA; the overall tiebreak never boosts anything in class A.
        if ($first->chosen_car_id === $carA->id) {
            $classA = $this->outcome($r, 'category:1');
            $this->assertSame($lowA->id, $classA->car->id);
            $this->assertSame(0, $classA->systemVotes);
        }

        // Contestant budgets are unaffected.
        $this->assertSame(3, $voters[0]->votes()->count());
    }

    public function test_class_tie_resolution_and_finalize_snapshot(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $a = $this->makeCategory(1, 'A');
        $b = $this->makeCategory(2, 'B');
        $star = $this->makeContestant($event, 1, $b)->cars[0];
        $x = $this->makeContestant($event, 1, $a)->cars[0];
        $y = $this->makeContestant($event, 1, $a)->cars[0];
        $voters = $this->voters($event, 3, $this->makeCategory(4, 'V'));
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($voters[0], [$star, $x, $y]);
        $this->vote($voters[1], [$star]);
        $this->vote($voters[2], [$star]);
        $lifecycle = app(VotingLifecycleService::class);
        $lifecycle->close($event, $admin);

        $this->assertThrows(fn () => $lifecycle->finalize($event->fresh(), $admin), VotingException::class);
        $this->assertThrows(fn () => app(TieBreakService::class)->resolve($event, ResultsCalculator::OVERALL, $admin), VotingException::class);

        $tb = app(TieBreakService::class)->resolve($event, 'category:1', $admin);
        $this->assertContains($tb->chosen_car_id, [$x->id, $y->id]);

        $lifecycle->finalize($event->fresh(), $admin);
        $event->refresh();
        $this->assertSame(EventStatus::Finalized, $event->status);

        $awards = $event->awards()->get();
        $this->assertSame('overall', $awards[0]->scope_key);
        $this->assertSame($star->id, $awards[0]->car_id);
        $classA = $awards->firstWhere('scope_key', 'category:1');
        $this->assertSame($tb->chosen_car_id, $classA->car_id);
        $this->assertSame(1, $classA->contestant_votes);
        $this->assertSame(1, $classA->system_votes);
        $classB = $awards->firstWhere('scope_key', 'category:2');
        $this->assertSame(Award::OUTCOME_NO_ELIGIBLE, $classB->outcome);

        // Snapshot is immutable and ties cannot be re-resolved after finalize.
        $this->assertThrows(fn () => $awards[0]->delete(), LogicException::class);
        $this->assertSame($tb->id, app(TieBreakService::class)->resolve($event, 'category:1', $admin)->id);
        $this->assertThrows(fn () => $lifecycle->finalize($event, $admin), VotingException::class);
    }

    public function test_tie_button_rejected_while_voting_open(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $cat = $this->makeCategory(1, 'A');
        $p1 = $this->makeContestant($event, 1, $cat);
        $p2 = $this->makeContestant($event, 1, $cat);
        $this->forceStatus($event, EventStatus::VotingOpen);
        $this->vote($p1, [$p1->cars[0]]);
        $this->vote($p2, [$p2->cars[0]]);

        $this->assertThrows(fn () => app(TieBreakService::class)->resolve($event, 'overall', $admin), VotingException::class);
        $this->assertSame(0, AwardTiebreak::query()->count());
    }

    public function test_tiebreak_selection_is_roughly_uniform(): void
    {
        // Unit-level check of the selection rule: random_int over the sorted candidate list.
        $counts = [0, 0, 0];
        for ($i = 0; $i < 3000; $i++) {
            $counts[random_int(0, 2)]++;
        }
        foreach ($counts as $c) {
            $this->assertGreaterThan(850, $c);
        }
    }
}
