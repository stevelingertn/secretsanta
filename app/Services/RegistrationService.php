<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Contestant and car registration with the freeze rules from PLAN.md.
 * Lock order: event row first, then participant/car rows.
 */
class RegistrationService
{
    public const CONTACT_FIELDS = ['name', 'email', 'phone', 'address', 'city', 'state', 'zip'];

    public function __construct(private LoginCodeService $codes) {}

    /** Existing people who look like the one being added (name, last name, email or phone). */
    public function likelyMatches(Event $event, array $data): Collection
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phoneDigits = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));

        if ($name === '' && $email === '' && strlen($phoneDigits) < 7) {
            return collect();
        }

        return User::query()
            ->where('is_admin', false)
            ->where(function ($q) use ($name, $email, $phoneDigits) {
                if ($name !== '') {
                    $q->orWhere('name', 'like', '%'.addcslashes($name, '%_\\').'%');
                    $parts = preg_split('/\s+/', $name);
                    $last = end($parts);
                    if (count($parts) > 1 && strlen($last) >= 3) {
                        $q->orWhere('name', 'like', '%'.addcslashes($last, '%_\\'));
                    }
                }
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
                if (strlen($phoneDigits) >= 7) {
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '-', ''), ' ', ''), '(', ''), ')', ''), '.', '') LIKE ?",
                        ['%'.substr($phoneDigits, -7)]
                    );
                }
            })
            ->with(['participants' => fn ($q) => $q->where('event_id', $event->id)->withCount('cars')])
            ->orderBy('name')
            ->limit(10)
            ->get();
    }

    /** @return array{0: Participant, 1: string} the participant and its plain login code (show once, never log) */
    public function createContestant(Event $event, array $contact, ?User $admin, ?User $existingUser = null): array
    {
        return DB::transaction(function () use ($event, $contact, $admin, $existingUser) {
            $locked = $this->lockEventShared($event);
            if (! $locked->allowsRegistration()) {
                throw new VotingException('Registration is closed for this event.');
            }

            $user = $existingUser;
            if (! $user) {
                $user = new User;
                $user->fill(Arr::only($contact, self::CONTACT_FIELDS));
                $user->save();
            } elseif ($user->participants()->where('event_id', $event->id)->exists()) {
                throw new VotingException('This person is already registered for this event.');
            }

            $participant = new Participant;
            $participant->event_id = $locked->id;
            $participant->user_id = $user->id;
            $code = $this->codes->assignNew($participant);
            $participant->save();

            AuditLog::record('contestant.created', $locked, $admin, $participant);

            return [$participant->setRelation('user', $user)->setRelation('event', $locked), $code];
        }, 3);
    }

    public function nextEntryNumber(Event $event): int
    {
        return (int) Car::query()->where('event_id', $event->id)->max('entry_number') + 1;
    }

    /**
     * @param  array{category_id:int, entry_number?:int|null, year?:int|null, make?:string|null, model?:string|null, description?:string|null}  $data
     */
    public function registerCar(Participant $participant, array $data, ?User $admin): Car
    {
        return DB::transaction(function () use ($participant, $data, $admin) {
            $event = $this->lockEventShared($participant->event);
            if (! $event->allowsRegistration()) {
                throw new VotingException('Registration is closed for this event.');
            }
            $locked = Participant::query()->whereKey($participant->id)->lockForUpdate()->firstOrFail();

            if (! Category::query()->whereKey($data['category_id'] ?? 0)->exists()) {
                throw new VotingException('Choose a valid class.');
            }

            $number = isset($data['entry_number']) && $data['entry_number'] !== '' ? (int) $data['entry_number'] : null;
            if ($number !== null && $number < 1) {
                throw new VotingException('Car numbers start at 1.');
            }
            if ($number !== null && Car::query()->where('event_id', $event->id)->where('entry_number', $number)->exists()) {
                throw new VotingException("Car #{$number} is already registered.");
            }

            $car = new Car;
            $car->event_id = $event->id;
            $car->participant_id = $locked->id;
            $car->category_id = (int) $data['category_id'];
            $car->entry_number = $number ?? $this->nextEntryNumber($event);
            $this->fillVehicle($car, $data);

            try {
                $car->save();
            } catch (UniqueConstraintViolationException) {
                throw new VotingException("Car #{$car->entry_number} was just taken. Save again to get the next number.");
            }

            $this->ensureVoterNumber($locked);
            AuditLog::record('car.registered', $event, $admin, $car, ['entry_number' => $car->entry_number, 'participant_id' => $locked->id]);

            return $car;
        }, 3);
    }

    public function updateCar(Car $car, array $data, ?User $admin): Car
    {
        return DB::transaction(function () use ($car, $data, $admin) {
            $event = Event::query()->whereKey($car->event_id)->lockForUpdate()->firstOrFail();
            $car = Car::query()->whereKey($car->id)->lockForUpdate()->firstOrFail();

            if ($event->status === EventStatus::Finalized) {
                throw new VotingException('Results are final. Cars can no longer be edited.');
            }

            $structural = [];
            if (array_key_exists('category_id', $data) && (int) $data['category_id'] !== $car->category_id) {
                $structural['category_id'] = (int) $data['category_id'];
            }
            if (array_key_exists('participant_id', $data) && (int) $data['participant_id'] !== $car->participant_id) {
                $structural['participant_id'] = (int) $data['participant_id'];
            }
            if (isset($data['entry_number']) && $data['entry_number'] !== '' && (int) $data['entry_number'] !== $car->entry_number) {
                $structural['entry_number'] = (int) $data['entry_number'];
            }

            if ($structural && ! $event->allowsStructuralCarChanges()) {
                throw new VotingException('Car number, owner and class are locked once voting opens.');
            }

            $oldOwnerId = $car->participant_id;
            if (isset($structural['category_id'])) {
                if (! Category::query()->whereKey($structural['category_id'])->exists()) {
                    throw new VotingException('Choose a valid class.');
                }
                $car->category_id = $structural['category_id'];
            }
            if (isset($structural['participant_id'])) {
                $newOwner = Participant::query()->where('event_id', $event->id)->whereKey($structural['participant_id'])->lockForUpdate()->first();
                if (! $newOwner) {
                    throw new VotingException('Choose an owner registered for this event.');
                }
                $car->participant_id = $newOwner->id;
            }
            if (isset($structural['entry_number'])) {
                if ($structural['entry_number'] < 1) {
                    throw new VotingException('Car numbers start at 1.');
                }
                if (Car::query()->where('event_id', $event->id)->where('entry_number', $structural['entry_number'])->exists()) {
                    throw new VotingException("Car #{$structural['entry_number']} is already registered.");
                }
                $car->entry_number = $structural['entry_number'];
            }

            $this->fillVehicle($car, array_merge($car->only(['year', 'make', 'model', 'description']), Arr::only($data, ['year', 'make', 'model', 'description'])));
            $car->save();

            if (isset($structural['participant_id']) || isset($structural['entry_number'])) {
                $this->refreshVoterNumber(Participant::findOrFail($oldOwnerId));
                $this->ensureVoterNumber(Participant::findOrFail($car->participant_id));
            }

            AuditLog::record('car.updated', $event, $admin, $car, $structural ? ['changed' => array_keys($structural)] : []);

            return $car;
        }, 3);
    }

    /** Only before voting opens, so no vote can reference the car. */
    public function deleteCar(Car $car, ?User $admin): void
    {
        DB::transaction(function () use ($car, $admin) {
            $event = Event::query()->whereKey($car->event_id)->lockForUpdate()->firstOrFail();
            if (! $event->allowsStructuralCarChanges()) {
                throw new VotingException('Cars cannot be removed once voting opens.');
            }
            $owner = Participant::findOrFail($car->participant_id);
            AuditLog::record('car.deleted', $event, $admin, $car, ['entry_number' => $car->entry_number]);
            $car->delete();
            $this->refreshVoterNumber($owner);
        });
    }

    /** Voter number: the owner's lowest car number when first assigned. Stable afterwards. */
    public function ensureVoterNumber(Participant $participant): void
    {
        if ($participant->voter_number !== null) {
            return;
        }
        $taken = Participant::query()->where('event_id', $participant->event_id)->whereNotNull('voter_number')->pluck('voter_number')->all();
        $number = Car::query()->where('participant_id', $participant->id)->whereNotIn('entry_number', $taken ?: [0])->min('entry_number');
        if ($number !== null) {
            $participant->voter_number = (int) $number;
            $participant->save();
        }
    }

    /** Setup-only corrections: if the voter number no longer matches an owned car, reassign it. */
    private function refreshVoterNumber(Participant $participant): void
    {
        if ($participant->voter_number === null) {
            return;
        }
        $owns = Car::query()->where('participant_id', $participant->id)->where('entry_number', $participant->voter_number)->exists();
        if (! $owns) {
            $participant->voter_number = null;
            $participant->save();
            $this->ensureVoterNumber($participant);
        }
    }

    private function fillVehicle(Car $car, array $data): void
    {
        $car->year = isset($data['year']) && $data['year'] !== '' ? (int) $data['year'] : null;
        $car->make = isset($data['make']) ? (trim((string) $data['make']) ?: null) : null;
        $car->model = isset($data['model']) ? (trim((string) $data['model']) ?: null) : null;
        $description = trim((string) ($data['description'] ?? ''));
        if ($description === '') {
            $description = trim(implode(' ', array_filter([$car->year, $car->make, $car->model])));
        }
        if ($description === '') {
            throw new VotingException('Enter the vehicle year, make and model, or a description.');
        }
        $car->description = mb_substr($description, 0, 255);
    }

    private function lockEventShared(Event $event): Event
    {
        return Event::query()->whereKey($event->id)->sharedLock()->firstOrFail();
    }
}
