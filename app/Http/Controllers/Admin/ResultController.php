<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\Award;
use App\Models\Event;
use App\Services\Results\AwardOutcome;
use App\Services\Results\ResultsCalculator;
use App\Services\TieBreakService;
use App\Services\VotingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Results after Voting Finished. Provisional results are computed from the closed tally plus
 * persisted tiebreaks (deterministic). Once finalized, only the stored award snapshot is shown.
 */
class ResultController extends AdminController
{
    public function show(Request $request, ResultsCalculator $calculator): View
    {
        $event = $this->event($request);

        return view('admin.results.show', $this->data($event, $calculator));
    }

    public function print(Request $request, ResultsCalculator $calculator): View
    {
        $event = $this->event($request);
        abort_unless($event->isClosedOrFinal(), 404);

        return view('admin.results.print', $this->data($event, $calculator) + ['generatedAt' => now()]);
    }

    public function tiebreak(Request $request, TieBreakService $tiebreaks): RedirectResponse
    {
        $event = $this->event($request);
        $scope = $request->validate(['scope' => ['required', 'string', 'regex:/^(overall|category:\d+)$/']])['scope'];

        try {
            $tiebreak = $tiebreaks->resolve($event, $scope, $request->user());
        } catch (VotingException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return redirect()->route('admin.results.show')->with('status', 'Tiebreaker recorded: car #'.$tiebreak->chosenCar->entry_number.' wins that award by one system vote.');
    }

    public function finalize(Request $request, VotingLifecycleService $lifecycle): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm the results are final.']);

        try {
            $lifecycle->finalize($this->event($request), $request->user());
        } catch (VotingException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return redirect()->route('admin.results.show')->with('status', 'Results are final.');
    }

    /** @return array{rows: list<array>, final: bool, pending: bool} */
    private function data(Event $event, ResultsCalculator $calculator): array
    {
        if ($event->status === EventStatus::Finalized) {
            $rows = $event->awards()->get()->map(fn (Award $a) => [
                'scope' => $a->scope_key,
                'title' => $a->scope_key === ResultsCalculator::OVERALL ? 'Best Overall' : $a->category_name,
                'status' => $a->outcome === Award::OUTCOME_WINNER ? AwardOutcome::WINNER : $a->outcome,
                'entry_number' => $a->entry_number,
                'vehicle' => $a->vehicle,
                'owner' => $a->owner_name,
                'class' => $a->category_name,
                'votes' => $a->contestant_votes,
                'system' => $a->system_votes,
                'explanation' => $a->explanation,
                'candidates' => [],
                'runners' => [],
                'can_resolve' => false,
            ])->all();

            return ['rows' => $rows, 'final' => true, 'pending' => false];
        }

        if (! $event->isClosedOrFinal()) {
            return ['rows' => [], 'final' => false, 'pending' => true];
        }

        $results = $calculator->calculate($event);
        $rows = array_map(fn (AwardOutcome $o) => [
            'scope' => $o->scopeKey,
            'title' => $o->title,
            'status' => $o->status,
            'entry_number' => $o->car?->entry_number,
            'vehicle' => $o->car?->description,
            'owner' => $o->car?->participant?->user?->name,
            'class' => $o->car?->category?->name,
            'votes' => $o->contestantVotes,
            'system' => $o->systemVotes,
            'explanation' => $o->explanation,
            'candidates' => array_map(fn ($c) => ['entry_number' => $c['car']->entry_number, 'vehicle' => $c['car']->description, 'votes' => $c['votes']], $o->status === AwardOutcome::WINNER && ! $o->tiebreak ? [] : $o->candidates),
            'runners' => array_map(fn ($c) => ['entry_number' => $c['car']->entry_number, 'votes' => $c['votes']], array_slice($o->ranking, 0, 4)),
            'can_resolve' => $o->canResolveTie(),
        ], [$results['overall'], ...$results['categories']]);

        return ['rows' => $rows, 'final' => false, 'pending' => $results['pending']];
    }
}
