<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Models\Car;
use App\Models\Participant;
use App\Models\Vote;
use App\Services\Results\ResultsCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends AdminController
{
    public function __invoke(Request $request, ResultsCalculator $calculator): View
    {
        $event = $request->attributes->get('event');
        if (! $event) {
            return view('admin.dashboard', ['stats' => null]);
        }

        $perCar = (int) $event->votes_per_car;
        $participants = Participant::query()->where('event_id', $event->id)->withCount(['cars', 'votes'])->get(['id', 'allowance_override']);
        $effective = $participants->sum(fn ($p) => $p->allowance_override ?? $p->cars_count * $perCar);

        $bySource = Vote::query()->where('event_id', $event->id)
            ->select('source', DB::raw('COUNT(*) as total'))->groupBy('source')->pluck('total', 'source');
        $cast = (int) $bySource->sum();

        $byClass = Car::query()->where('cars.event_id', $event->id)
            ->join('categories', 'categories.id', '=', 'cars.category_id')
            ->select('categories.name', DB::raw('COUNT(*) as total'))
            ->groupBy('categories.id', 'categories.name')->orderByDesc('total')->orderBy('categories.name')->get();

        $results = in_array($event->status, [EventStatus::VotingClosed, EventStatus::Finalized], true)
            ? $calculator->calculate($event) : null;

        return view('admin.dashboard', ['stats' => [
            'cars' => (int) $byClass->sum('total'),
            'contestants' => $participants->count(),
            'byClass' => $byClass,
            'cast' => $cast,
            'online' => (int) ($bySource['online'] ?? 0),
            'manual' => (int) ($bySource['manual'] ?? 0),
            'defaultCapacity' => $participants->sum('cars_count') * $perCar,
            'effective' => $effective,
            'remaining' => max(0, $effective - $cast),
            'overrides' => $participants->whereNotNull('allowance_override')->count(),
            'pendingTies' => $results ? collect([$results['overall'], ...$results['categories']])->filter->canResolveTie()->count() : null,
            'resultsPending' => $results['pending'] ?? null,
        ]]);
    }
}
