<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Models\Award;
use App\Models\AwardTiebreak;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Participant;
use App\Models\Vote;
use App\Services\Results\AwardOutcome;
use App\Services\Results\ResultsCalculator;
use Illuminate\Support\Collection;

/**
 * Pure data builders for the admin reports. No HTTP or view concerns here.
 * Contestant vote counts never include award-scoped tiebreak (system) votes;
 * those are always reported separately with their award scope.
 */
class ReportService
{
    public function __construct(private ResultsCalculator $calculator) {}

    /**
     * Every car with contestant vote subtotals (online/manual) plus any tiebreak
     * system votes that apply to that car, labeled with their award scope.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function votesByCar(Event $event, ?int $categoryId = null, ?string $search = null): Collection
    {
        $cars = Car::query()->where('event_id', $event->id)
            ->with('category')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($search, fn ($q) => $q->search($search))
            ->orderBy('entry_number')
            ->get();

        $online = Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Online->value)
            ->selectRaw('car_id, COUNT(*) as total')->groupBy('car_id')->pluck('total', 'car_id');
        $manual = Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Manual->value)
            ->selectRaw('car_id, COUNT(*) as total')->groupBy('car_id')->pluck('total', 'car_id');

        $tiebreaksByCar = $this->tiebreaksByCar($event);

        return $cars->map(function (Car $car) use ($online, $manual, $tiebreaksByCar) {
            $onlineVotes = (int) ($online[$car->id] ?? 0);
            $manualVotes = (int) ($manual[$car->id] ?? 0);

            return [
                'car' => $car,
                'entry_number' => $car->entry_number,
                'vehicle' => $car->vehicle(),
                'class' => $car->category?->name,
                'online_votes' => $onlineVotes,
                'manual_votes' => $manualVotes,
                'contestant_votes' => $onlineVotes + $manualVotes,
                'tiebreak_votes' => $tiebreaksByCar[$car->id] ?? [],
            ];
        })->values();
    }

    /**
     * Award-scoped tiebreak (system) votes for a car, e.g. [['scope_label' => 'Best Overall', 'votes' => 1]].
     *
     * @return array<int, array<int, array{scope_key: string, scope_label: string, votes: int}>>
     */
    private function tiebreaksByCar(Event $event): array
    {
        $categoryNames = Category::query()->pluck('name', 'id');

        $out = [];
        foreach (AwardTiebreak::query()->where('event_id', $event->id)->get() as $tiebreak) {
            $label = $tiebreak->scope_key === ResultsCalculator::OVERALL
                ? 'Best Overall'
                : ($categoryNames[$tiebreak->category_id] ?? 'Class').' class';

            $out[$tiebreak->chosen_car_id][] = [
                'scope_key' => $tiebreak->scope_key,
                'scope_label' => $label,
                'votes' => (int) $tiebreak->system_votes,
            ];
        }

        return $out;
    }

    /**
     * Per class: ranked cars with contestant vote counts, class total, and the award outcome.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function votesByCategory(Event $event): Collection
    {
        $results = $this->calculator->calculate($event);
        $tally = $results['tally'];

        $cars = Car::query()->where('event_id', $event->id)->with('category')->orderBy('entry_number')->get();
        $categories = Category::query()->orderBy('id')->get()
            ->filter(fn (Category $c) => $cars->contains(fn (Car $car) => $car->category_id === $c->id))
            ->values();

        return $categories->map(function (Category $category) use ($cars, $tally, $results) {
            $inClass = $cars->where('category_id', $category->id)->values();

            $ranked = $inClass->map(fn (Car $car) => ['car' => $car, 'votes' => $tally[$car->id] ?? 0])
                ->sortBy([['votes', 'desc'], fn ($a, $b) => $a['car']->entry_number <=> $b['car']->entry_number])
                ->values();

            $outcome = collect($results['categories'])->first(fn (AwardOutcome $o) => $o->category?->id === $category->id);

            return [
                'category' => $category,
                'cars' => $ranked,
                'total_votes' => $ranked->sum('votes'),
                'outcome' => $outcome,
            ];
        })->values();
    }

    /**
     * Registered car count per class plus a grand total. Only classes with at least one entry.
     *
     * @return array{rows: Collection<int, array{category: Category, count: int}>, total: int}
     */
    public function entriesByClass(Event $event): array
    {
        $counts = Car::query()->where('event_id', $event->id)
            ->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');

        $categories = Category::query()->whereIn('id', $counts->keys())->orderBy('name')->get();

        $rows = $categories->map(fn (Category $c) => [
            'category' => $c,
            'count' => (int) ($counts[$c->id] ?? 0),
        ])->values();

        return ['rows' => $rows, 'total' => (int) $counts->sum()];
    }

    /**
     * Best Overall then each class. Finalized events use the immutable Award snapshot;
     * otherwise computed live from ResultsCalculator and marked provisional.
     *
     * @return array{finalized: bool, rows: Collection<int, array<string, mixed>>}
     */
    public function awards(Event $event): array
    {
        if ($event->status === EventStatus::Finalized) {
            $rows = Award::query()->where('event_id', $event->id)->orderBy('position')->get()
                ->map(fn (Award $award) => [
                    'title' => $award->scope_key === ResultsCalculator::OVERALL ? 'Best Overall' : $award->category_name,
                    'outcome' => $award->outcome,
                    'entry_number' => $award->entry_number,
                    'vehicle' => $award->vehicle,
                    'owner_name' => $award->owner_name,
                    'contestant_votes' => $award->contestant_votes,
                    'system_votes' => $award->system_votes,
                    'explanation' => $award->explanation,
                ]);

            return ['finalized' => true, 'rows' => $rows];
        }

        $results = $this->calculator->calculate($event);

        $rows = collect([$results['overall'], ...$results['categories']])->map(function (AwardOutcome $outcome) {
            $car = $outcome->car;

            return [
                'title' => $outcome->title,
                'outcome' => $outcome->status,
                'entry_number' => $car?->entry_number,
                'vehicle' => $car?->vehicle(),
                'owner_name' => $car?->participant?->user?->name,
                'contestant_votes' => $car ? $outcome->contestantVotes : 0,
                'system_votes' => $outcome->systemVotes,
                'explanation' => $outcome->explanation,
            ];
        });

        return ['finalized' => false, 'rows' => $rows];
    }

    /**
     * Capacity, allowance, and vote-count reconciliation for the event.
     *
     * @return array<string, mixed>
     */
    public function reconciliation(Event $event): array
    {
        $registeredCars = Car::query()->where('event_id', $event->id)->count();
        $defaultCapacity = $registeredCars * (int) $event->votes_per_car;

        $participants = Participant::query()->where('event_id', $event->id)
            ->withCount(['cars', 'votes'])->get();

        $contestants = $participants->count();
        $overrides = $participants->filter(fn (Participant $p) => $p->allowance_override !== null)->count();

        $effectiveCapacity = 0;
        $overCapacityCount = 0;
        foreach ($participants as $participant) {
            $allowance = $participant->allowance_override ?? ($participant->cars_count * (int) $event->votes_per_car);
            $effectiveCapacity += $allowance;
            if ($participant->votes_count > $allowance) {
                $overCapacityCount++;
            }
        }

        $onlineVotes = Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Online->value)->count();
        $manualVotes = Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Manual->value)->count();
        $totalVotes = $onlineVotes + $manualVotes;

        $tiebreaks = AwardTiebreak::query()->where('event_id', $event->id)->with('chosenCar')->get();
        $categoryNames = Category::query()->pluck('name', 'id');
        $tiebreakRows = $tiebreaks->map(fn (AwardTiebreak $t) => [
            'scope_label' => $t->scope_key === ResultsCalculator::OVERALL
                ? 'Best Overall'
                : ($categoryNames[$t->category_id] ?? 'Class').' class',
            'car' => $t->chosenCar,
            'votes' => (int) $t->system_votes,
        ]);

        return [
            'registered_cars' => $registeredCars,
            'default_capacity' => $defaultCapacity,
            'contestants' => $contestants,
            'contestants_with_override' => $overrides,
            'effective_capacity' => $effectiveCapacity,
            'online_votes' => $onlineVotes,
            'manual_votes' => $manualVotes,
            'contestant_votes' => $totalVotes,
            'remaining_capacity' => max(0, $effectiveCapacity - $totalVotes),
            'within_capacity' => $totalVotes <= $effectiveCapacity,
            'over_capacity_participants' => $overCapacityCount,
            'sanity_passed' => $overCapacityCount === 0,
            'tiebreak_votes' => $tiebreakRows,
        ];
    }
}
