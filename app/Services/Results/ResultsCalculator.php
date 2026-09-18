<?php

namespace App\Services\Results;

use App\Models\AwardTiebreak;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Vote;
use Illuminate\Support\Collection;
use LogicException;

/**
 * Derives awards from contestant vote counts plus persisted, award-scoped tiebreaks.
 * Deterministic: the same closed tally and tiebreak rows always give the same answer.
 *
 * 1. Best Overall: most contestant votes (at least 1). Ties need an admin tiebreak.
 * 2. Each class: most votes among its cars excluding the Best Overall winner.
 *    A class containing a car in an unresolved Best Overall tie waits for that tie.
 * A tiebreak's +1 system vote applies only inside its own award scope.
 */
class ResultsCalculator
{
    public const OVERALL = 'overall';

    public static function categoryScope(int $categoryId): string
    {
        return 'category:'.$categoryId;
    }

    /** @return array<int, int> car_id => contestant vote count (cars with no votes omitted) */
    public function tally(Event $event): array
    {
        return Vote::query()->where('event_id', $event->id)
            ->selectRaw('car_id, COUNT(*) as total')->groupBy('car_id')->orderBy('car_id')
            ->pluck('total', 'car_id')->map(fn ($v) => (int) $v)->all();
    }

    public function tallyHash(Event $event): string
    {
        return hash('sha256', json_encode($this->tally($event)));
    }

    /** @return array{overall: AwardOutcome, categories: list<AwardOutcome>, pending: bool, tally: array<int,int>} */
    public function calculate(Event $event): array
    {
        $tally = $this->tally($event);
        $cars = Car::query()->where('event_id', $event->id)->with(['category', 'participant.user'])->orderBy('entry_number')->get();
        $tiebreaks = AwardTiebreak::query()->where('event_id', $event->id)->get()->keyBy('scope_key');

        $votesFor = fn (Car $car) => $tally[$car->id] ?? 0;

        $overall = $this->decide(self::OVERALL, 'Best Overall', null, $cars, $votesFor, $tiebreaks->get(self::OVERALL));
        $overallTiedIds = $overall->status === AwardOutcome::TIE_PENDING
            ? array_map(fn ($c) => $c['car']->id, $overall->candidates) : [];

        $categoryIds = $cars->pluck('category_id')->unique();
        $categories = Category::query()->orderBy('id')->get()
            ->filter(fn (Category $c) => $categoryIds->contains($c->id));

        $outcomes = [];
        foreach ($categories as $category) {
            $scope = self::categoryScope($category->id);
            $inClass = $cars->where('category_id', $category->id)->values();

            if ($overallTiedIds && $inClass->contains(fn (Car $c) => in_array($c->id, $overallTiedIds, true))) {
                $outcomes[] = new AwardOutcome($scope, $category->name, $category, AwardOutcome::WAITING,
                    explanation: 'Waiting for the Best Overall tiebreak, because a car in this class is tied for Best Overall.');

                continue;
            }

            $eligible = $overall->car ? $inClass->reject(fn (Car $c) => $c->id === $overall->car->id)->values() : $inClass;
            $outcome = $this->decide($scope, $category->name, $category, $eligible, $votesFor, $tiebreaks->get($scope));

            if ($outcome->status === AwardOutcome::NO_VOTES && $overall->car && $overall->car->category_id === $category->id) {
                $outcome = new AwardOutcome($scope, $category->name, $category, AwardOutcome::NO_ELIGIBLE,
                    explanation: 'No winner: the only car in this class with votes won Best Overall.');
            }
            $outcomes[] = $outcome;
        }

        $pending = $overall->isPending() || collect($outcomes)->contains(fn (AwardOutcome $o) => $o->isPending());

        return ['overall' => $overall, 'categories' => $outcomes, 'pending' => $pending, 'tally' => $tally];
    }

    public function findOutcome(array $results, string $scopeKey): ?AwardOutcome
    {
        if ($scopeKey === self::OVERALL) {
            return $results['overall'];
        }

        return collect($results['categories'])->first(fn (AwardOutcome $o) => $o->scopeKey === $scopeKey);
    }

    /** @param Collection<int, Car> $cars */
    private function decide(string $scope, string $title, ?Category $category, Collection $cars, callable $votesFor, ?AwardTiebreak $tiebreak): AwardOutcome
    {
        $ranking = $cars->map(fn (Car $car) => ['car' => $car, 'votes' => $votesFor($car)])
            ->filter(fn ($row) => $row['votes'] > 0)
            ->sort(fn ($a, $b) => [$b['votes'], $a['car']->entry_number] <=> [$a['votes'], $b['car']->entry_number])
            ->values()->all();

        if ($ranking === []) {
            $explanation = $scope === self::OVERALL
                ? 'No winner: no contestant votes were cast.'
                : ($cars->isEmpty() ? 'No winner: no eligible cars in this class.' : 'No winner: no car in this class received a contestant vote.');

            return new AwardOutcome($scope, $title, $category, AwardOutcome::NO_VOTES, ranking: [], explanation: $explanation);
        }

        $top = $ranking[0]['votes'];
        $tied = array_values(array_filter($ranking, fn ($row) => $row['votes'] === $top));

        if (count($tied) === 1) {
            return new AwardOutcome($scope, $title, $category, AwardOutcome::WINNER, $tied[0]['car'], $top, 0, $tied, $ranking);
        }

        if ($tiebreak) {
            $tiedIds = collect($tied)->map(fn ($r) => $r['car']->id)->sort()->values()->all();
            $recorded = collect($tiebreak->candidates)->pluck('car_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
            if ($tiedIds !== $recorded || $tiebreak->tied_votes !== $top) {
                throw new LogicException("Recorded tiebreak for {$scope} no longer matches the closed tally.");
            }
            $winner = collect($tied)->first(fn ($r) => $r['car']->id === (int) $tiebreak->chosen_car_id);

            return new AwardOutcome($scope, $title, $category, AwardOutcome::WINNER, $winner['car'], $top, (int) $tiebreak->system_votes, $tied, $ranking, $tiebreak);
        }

        return new AwardOutcome($scope, $title, $category, AwardOutcome::TIE_PENDING, null, $top, 0, $tied, $ranking,
            explanation: count($tied).' cars are tied with '.$top.' votes each. An admin must run the tiebreaker.');
    }
}
