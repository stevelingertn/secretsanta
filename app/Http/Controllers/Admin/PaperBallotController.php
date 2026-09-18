<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoteSource;
use App\Exceptions\VotingException;
use App\Models\Participant;
use App\Services\AllowanceService;
use App\Services\LoginCodeService;
use App\Services\VoteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Paper ballot entry: find the existing contestant (never create one here), type the car
 * numbers, preview, confirm. Uses the same VoteService as online voting.
 */
class PaperBallotController extends AdminController
{
    public function index(Request $request): View
    {
        $this->event($request);

        return view('admin.paper.index', ['q' => '', 'matches' => collect()]);
    }

    public function find(Request $request, LoginCodeService $codes): View|RedirectResponse
    {
        $event = $this->event($request);
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'max:60']])['q']);
        $matches = collect();

        if ($q !== '') {
            $number = ltrim($q, '#');
            if (ctype_digit($number)) {
                $byNumber = Participant::query()->where('event_id', $event->id)
                    ->where(fn ($w) => $w->where('voter_number', (int) $number)
                        ->orWhereHas('cars', fn ($c) => $c->where('entry_number', (int) $number)))
                    ->get();
                if ($byNumber->count() === 1) {
                    return redirect()->route('admin.paper.create', $byNumber->first());
                }
                $matches = $byNumber;
            } elseif ($found = $codes->findParticipant($q, $event)) {
                return redirect()->route('admin.paper.create', $found);
            } else {
                $matches = Participant::query()->where('event_id', $event->id)
                    ->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($q, '%_\\').'%'))
                    ->limit(25)->get();
            }
            $matches->load(['user:id,name', 'cars:id,participant_id,entry_number']);
        }

        return view('admin.paper.index', ['q' => $q, 'matches' => $matches]);
    }

    public function create(Request $request, Participant $participant, AllowanceService $allowances): View
    {
        $this->ensureInEvent($request, $participant);

        return view('admin.paper.create', $this->panel($participant, $allowances) + [
            'input' => (string) old('car_numbers', ''),
            'preview' => null,
        ]);
    }

    public function preview(Request $request, Participant $participant, VoteService $votes, AllowanceService $allowances): View
    {
        $this->ensureInEvent($request, $participant);
        $data = $request->validate(['car_numbers' => ['required', 'string', 'max:2000']], ['car_numbers.required' => 'Type the car numbers from the ballot.']);

        $tokens = $votes->parseEntryList($data['car_numbers']);

        return view('admin.paper.create', $this->panel($participant, $allowances) + [
            'input' => $data['car_numbers'],
            'tokens' => $tokens,
            'preview' => $votes->preview($participant, $tokens),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, Participant $participant, VoteService $votes): RedirectResponse
    {
        $this->ensureInEvent($request, $participant);
        $data = $request->validate([
            'tokens' => ['required', 'array', 'min:1'],
            'tokens.*' => ['string', 'max:20'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        try {
            $submission = $votes->submit($participant, $data['tokens'], VoteSource::Manual, $request->user(), $data['idempotency_key']);
        } catch (VotingException $e) {
            return redirect()->route('admin.paper.create', $participant)
                ->withErrors([$e->getMessage(), ...$e->errors])
                ->withInput(['car_numbers' => implode(' ', $data['tokens'])]);
        }

        $name = $participant->user->name;

        return redirect()->route('admin.paper.index')->with('status',
            "Saved {$submission->vote_count} ".Str::plural('vote', $submission->vote_count)." for {$name}".($participant->voter_number ? " (voter #{$participant->voter_number})" : '').'. Ready for the next ballot.');
    }

    private function panel(Participant $participant, AllowanceService $allowances): array
    {
        $participant->load(['user', 'cars']);

        return [
            'participant' => $participant,
            'summary' => $allowances->summary($participant),
            'votedNumbers' => $participant->votes()->join('cars', 'cars.id', '=', 'votes.car_id')
                ->orderBy('cars.entry_number')->get(['cars.entry_number', 'votes.source'])
                ->map(fn ($v) => ['number' => $v->entry_number, 'source' => $v->source->label()]),
        ];
    }
}
