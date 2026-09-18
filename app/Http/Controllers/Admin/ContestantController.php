<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VotingException;
use App\Http\Requests\Admin\StoreContestantRequest;
use App\Http\Requests\Admin\UpdateAllowanceRequest;
use App\Http\Requests\Admin\UpdateContestantRequest;
use App\Models\Category;
use App\Models\Participant;
use App\Services\AllowanceService;
use App\Services\LoginCodeService;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ContestantController extends AdminController
{
    public function __construct(
        private RegistrationService $registration,
        private AllowanceService $allowance,
        private LoginCodeService $codes,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Participant::class);
        $event = $this->event($request);

        $q = trim((string) $request->query('q', ''));

        $participants = Participant::query()
            ->where('event_id', $event->id)
            ->with(['user', 'cars'])
            ->withCount('votes')
            ->when($q !== '', function ($query) use ($q) {
                $number = ltrim($q, '#');
                $query->where(function ($sub) use ($q, $number) {
                    $sub->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.addcslashes($q, '%_\\').'%'));
                    if (ctype_digit($number)) {
                        $sub->orWhere('voter_number', (int) $number);
                        $sub->orWhereHas('cars', fn ($c) => $c->where('entry_number', (int) $number));
                    }
                });
            })
            ->orderBy('voter_number')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.contestants.index', [
            'event' => $event,
            'participants' => $participants,
            'q' => $q,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Participant::class);
        $event = $this->event($request);

        return view('admin.contestants.create', [
            'event' => $event,
            'categories' => Category::query()->orderBy('name')->get(),
            'nextEntryNumber' => $this->registration->nextEntryNumber($event),
            'matches' => collect(),
            'old' => old(),
        ]);
    }

    public function store(StoreContestantRequest $request): RedirectResponse|View
    {
        Gate::authorize('create', Participant::class);
        $event = $this->event($request);
        $data = $request->validated();

        $existingUserId = $request->integer('existing_user_id') ?: null;
        $confirmNew = $request->boolean('confirm_new');

        if (! $existingUserId && ! $confirmNew) {
            $matches = $this->registration->likelyMatches($event, $data);
            if ($matches->isNotEmpty()) {
                return view('admin.contestants.create', [
                    'event' => $event,
                    'categories' => Category::query()->orderBy('name')->get(),
                    'nextEntryNumber' => $this->registration->nextEntryNumber($event),
                    'matches' => $matches,
                    'old' => $data,
                ]);
            }
        }

        $existingUser = null;
        if ($existingUserId) {
            $existingUser = \App\Models\User::query()->where('is_admin', false)->find($existingUserId);
        }

        try {
            [$participant] = $this->registration->createContestant($event, $data, $request->user(), $existingUser);
            $this->registration->registerCar($participant, $data, $request->user());
        } catch (VotingException $e) {
            return back()->withErrors(['registration' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.contestants.show', $participant)
            ->with('status', 'Registered. Print their ballot.');
    }

    public function show(Request $request, Participant $participant): View
    {
        $participant = $this->ensureInEvent($request, $participant);
        Gate::authorize('view', $participant);
        $event = $this->event($request);

        $participant->load(['user', 'cars.category']);

        return view('admin.contestants.show', [
            'event' => $event,
            'participant' => $participant,
            'summary' => $this->allowance->summary($participant),
            'categories' => Category::query()->orderBy('name')->get(),
            'nextEntryNumber' => $this->registration->nextEntryNumber($event),
        ]);
    }

    public function edit(Request $request, Participant $participant): View
    {
        $participant = $this->ensureInEvent($request, $participant);
        Gate::authorize('update', $participant);
        $participant->load('user');

        return view('admin.contestants.edit', [
            'event' => $this->event($request),
            'participant' => $participant,
        ]);
    }

    public function update(UpdateContestantRequest $request, Participant $participant): RedirectResponse
    {
        $participant = $this->ensureInEvent($request, $participant);
        Gate::authorize('update', $participant);

        $participant->user->fill($request->validated())->save();

        return redirect()->route('admin.contestants.show', $participant)->with('status', 'Contact details updated.');
    }

    public function rotateCode(Request $request, Participant $participant): RedirectResponse
    {
        $participant = $this->ensureInEvent($request, $participant);
        Gate::authorize('update', $participant);

        $this->codes->rotate($participant, $request->user());

        return redirect()->route('admin.contestants.show', $participant)
            ->with('status', 'Login code rotated. The old code no longer works and any signed-in session for this contestant has ended. Votes were not changed.');
    }

    public function updateAllowance(UpdateAllowanceRequest $request, Participant $participant): RedirectResponse
    {
        $participant = $this->ensureInEvent($request, $participant);
        Gate::authorize('update', $participant);

        $total = $request->boolean('reset') ? null : $request->integer('total');

        try {
            $this->allowance->setOverride($participant, $total, (string) $request->input('reason'), $request->user());
        } catch (VotingException $e) {
            return back()->withErrors(['allowance' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.contestants.show', $participant)->with('status', 'Allowance updated.');
    }
}
