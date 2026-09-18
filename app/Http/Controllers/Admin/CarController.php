<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\VotingException;
use App\Http\Requests\Admin\StoreCarRequest;
use App\Http\Requests\Admin\UpdateCarRequest;
use App\Models\Car;
use App\Models\Category;
use App\Models\Participant;
use App\Services\CarPhotoService;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CarController extends AdminController
{
    public function __construct(
        private RegistrationService $registration,
        private CarPhotoService $photos,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Car::class);
        $event = $this->event($request);

        $q = trim((string) $request->query('q', ''));
        $classId = $request->query('class');

        $cars = Car::query()
            ->where('event_id', $event->id)
            ->with(['category', 'participant.user'])
            ->withCount('votes')
            ->search($q)
            ->when($classId, fn ($query) => $query->where('category_id', $classId))
            ->orderBy('entry_number')
            ->paginate(50)
            ->withQueryString();

        return view('admin.cars.index', [
            'event' => $event,
            'cars' => $cars,
            'q' => $q,
            'classId' => $classId,
            'categories' => Category::query()->orderBy('name')->get(),
            'hasContestants' => Participant::query()->where('event_id', $event->id)->exists(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Car::class);
        $event = $this->event($request);

        $participants = Participant::query()
            ->where('event_id', $event->id)
            ->with('user')
            ->orderBy('voter_number')
            ->get();

        return view('admin.cars.create', [
            'event' => $event,
            'participants' => $participants,
            'categories' => Category::query()->orderBy('name')->get(),
            'nextEntryNumber' => $this->registration->nextEntryNumber($event),
            'selectedParticipantId' => $request->query('participant_id'),
        ]);
    }

    public function store(StoreCarRequest $request): RedirectResponse
    {
        Gate::authorize('create', Car::class);
        $event = $this->event($request);

        $participant = Participant::query()->where('event_id', $event->id)->find($request->integer('participant_id'));
        if (! $participant) {
            return back()->withErrors(['participant_id' => 'Choose a contestant registered for this event.'])->withInput();
        }

        try {
            $car = $this->registration->registerCar($participant, $request->validated(), $request->user());
            if ($request->hasFile('photo')) {
                $this->photos->store($car, $request->file('photo'));
            }
        } catch (VotingException $e) {
            return back()->withErrors(['registration' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.contestants.show', $participant)
            ->with('status', "Car #{$car->entry_number} registered.");
    }

    public function edit(Request $request, Car $car): View
    {
        $this->ensureCarInEvent($request, $car);
        Gate::authorize('update', $car);
        $event = $this->event($request);
        $car->load(['participant.user', 'category']);

        $participants = Participant::query()
            ->where('event_id', $event->id)
            ->with('user')
            ->orderBy('voter_number')
            ->get();

        return view('admin.cars.edit', [
            'event' => $event,
            'car' => $car,
            'participants' => $participants,
            'categories' => Category::query()->orderBy('name')->get(),
            'structuralLocked' => ! $event->allowsStructuralCarChanges(),
        ]);
    }

    public function update(UpdateCarRequest $request, Car $car): RedirectResponse
    {
        $this->ensureCarInEvent($request, $car);
        Gate::authorize('update', $car);

        $data = $request->validated();

        try {
            $car = $this->registration->updateCar($car, $data, $request->user());

            if ($request->boolean('remove_photo')) {
                $this->photos->delete($car);
                $car->photo_path = null;
                $car->thumb_path = null;
                $car->save();
            } elseif ($request->hasFile('photo')) {
                $this->photos->store($car, $request->file('photo'));
            }
        } catch (VotingException $e) {
            return back()->withErrors(['registration' => $e->getMessage()])->withInput();
        }

        return redirect()->route('admin.cars.edit', $car)->with('status', 'Car updated.');
    }

    public function destroy(Request $request, Car $car): RedirectResponse
    {
        $this->ensureCarInEvent($request, $car);
        Gate::authorize('delete', $car);

        $participant = $car->participant;

        try {
            $this->photos->delete($car);
            $this->registration->deleteCar($car, $request->user());
        } catch (VotingException $e) {
            return back()->withErrors(['registration' => $e->getMessage()]);
        }

        return redirect()->route('admin.contestants.show', $participant)->with('status', 'Car removed.');
    }

    private function ensureCarInEvent(Request $request, Car $car): void
    {
        abort_unless($car->event_id === $this->event($request)->id, 404);
    }
}
