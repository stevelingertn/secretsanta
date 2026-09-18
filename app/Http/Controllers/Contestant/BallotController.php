<?php

namespace App\Http\Controllers\Contestant;

use App\Enums\VoteSource;
use App\Exceptions\VotingException;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Participant;
use App\Services\AllowanceService;
use App\Services\VoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Online ballot: choose cars, review, confirm. Confirmed votes are final. */
class BallotController extends Controller
{
    public function index(Request $request, AllowanceService $allowances): View
    {
        /** @var Participant $participant */
        $participant = $request->attributes->get('participant');
        $event = $participant->event;

        $myVotes = $participant->votes()->with('car.category')->orderBy('created_at')->get();
        $votedCarIds = $myVotes->pluck('car_id')->all();

        $cars = $event->isVotingOpen()
            ? Car::query()->where('event_id', $event->id)->with('category')->orderBy('entry_number')->get()
            : collect();

        return view('contestant.ballot', [
            'participant' => $participant,
            'summary' => $allowances->summary($participant),
            'myVotes' => $myVotes,
            'votedCarIds' => $votedCarIds,
            'cars' => $cars,
            'initialQuery' => (string) $request->query('q', ''),
            'preselected' => array_map('intval', (array) old('cars', $request->query('cars', []))),
        ]);
    }

    public function review(Request $request, VoteService $votes): View|RedirectResponse
    {
        /** @var Participant $participant */
        $participant = $request->attributes->get('participant');
        $data = $request->validate([
            'cars' => ['required', 'array', 'min:1', 'max:500'],
            'cars.*' => ['integer', 'min:1'],
        ], ['cars.required' => 'Choose at least one car.']);

        $preview = $votes->preview($participant, $data['cars']);
        if (! $preview['valid']) {
            return redirect()->route('ballot.index')->withErrors($preview['errors'])->withInput();
        }

        return view('contestant.review', [
            'participant' => $participant,
            'preview' => $preview,
            'numbers' => $data['cars'],
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, VoteService $votes): RedirectResponse
    {
        /** @var Participant $participant */
        $participant = $request->attributes->get('participant');
        $data = $request->validate([
            'cars' => ['required', 'array', 'min:1', 'max:500'],
            'cars.*' => ['integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $submission = $votes->submit($participant, $data['cars'], VoteSource::Online, null, $data['idempotency_key']);
        } catch (VotingException $e) {
            return redirect()->route('ballot.index')->withErrors([$e->getMessage(), ...$e->errors])->withInput(['cars' => $data['cars']]);
        }

        return redirect()->route('ballot.index')
            ->with('status', 'Saved. '.$submission->vote_count.' '.Str::plural('vote', $submission->vote_count).' recorded.');
    }
}
