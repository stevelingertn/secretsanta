<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use App\Models\Participant;
use App\Services\AllowanceService;
use App\Services\LoginCodeService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BallotPrintController extends AdminController
{
    public function __construct(
        private AllowanceService $allowance,
        private LoginCodeService $codes,
    ) {}

    public function show(Request $request, Participant $participant): View
    {
        $participant = $this->ensureInEvent($request, $participant);
        $event = $this->event($request);
        $participant->load(['user', 'cars.category']);

        AuditLog::record('ballot.printed', $event, $request->user(), $participant);

        return view('admin.ballots.show', [
            'event' => $event,
            'participant' => $participant,
            'summary' => $this->allowance->summary($participant),
            'code' => $this->codes->reveal($participant),
            'back' => route('admin.contestants.show', $participant),
        ]);
    }

    public function all(Request $request): View
    {
        $event = $this->event($request);

        $participants = Participant::query()
            ->where('event_id', $event->id)
            ->with(['user', 'cars.category'])
            ->orderByRaw('voter_number is null, voter_number')
            ->get();

        $ballots = $participants->map(fn (Participant $participant) => [
            'participant' => $participant,
            'summary' => $this->allowance->summary($participant),
            'code' => $this->codes->reveal($participant),
        ]);

        foreach ($participants as $participant) {
            AuditLog::record('ballot.printed', $event, $request->user(), $participant);
        }

        return view('admin.ballots.all', [
            'event' => $event,
            'ballots' => $ballots,
            'back' => route('admin.contestants.index'),
        ]);
    }
}
