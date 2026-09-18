<?php

namespace Tests\Concerns;

use App\Enums\EventStatus;
use App\Enums\VoteSource;
use App\Models\BallotSubmission;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Participant;
use App\Models\User;
use App\Services\RegistrationService;
use App\Services\VoteService;
use Illuminate\Support\Str;

/** Synthetic fixtures for domain tests. Never uses real historical data. */
trait BuildsShow
{
    protected function makeEvent(EventStatus $status = EventStatus::Setup): Event
    {
        $event = Event::factory()->make();
        $event->status = $status;
        $event->is_active = true;
        $event->save();

        return $event;
    }

    protected function makeCategory(?int $id = null, ?string $name = null): Category
    {
        $id ??= (int) (Category::query()->max('id') ?? 100) + 1;

        return Category::query()->create(['id' => $id, 'name' => $name ?? 'Class '.$id]);
    }

    protected function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Registers a contestant and N cars. Temporarily uses setup state if the event is past it. */
    protected function makeContestant(Event $event, int $cars = 1, ?Category $category = null, ?string $name = null): Participant
    {
        $registration = app(RegistrationService::class);
        $category ??= Category::query()->first() ?? $this->makeCategory();

        $original = $event->status;
        $this->forceStatus($event, EventStatus::Setup);

        [$participant] = $registration->createContestant($event, ['name' => $name ?? fake()->name()], null);
        for ($i = 0; $i < $cars; $i++) {
            $registration->registerCar($participant, ['category_id' => $category->id, 'description' => 'Synthetic car '.Str::random(4)], null);
        }

        $this->forceStatus($event, $original);

        return $participant->fresh(['event', 'user']);
    }

    protected function addCar(Participant $owner, Category $category, ?int $number = null): Car
    {
        $event = $owner->event;
        $original = $event->status;
        $this->forceStatus($event, EventStatus::Setup);
        $car = app(RegistrationService::class)->registerCar($owner, ['category_id' => $category->id, 'entry_number' => $number, 'description' => 'Synthetic car'], null);
        $this->forceStatus($event, $original);

        return $car;
    }

    protected function forceStatus(Event $event, EventStatus $status): void
    {
        Event::query()->whereKey($event->id)->update(['status' => $status->value]);
        $event->status = $status;
    }

    /** @param list<int|Car> $cars */
    protected function vote(Participant $participant, array $cars, VoteSource $source = VoteSource::Online, ?User $admin = null): BallotSubmission
    {
        $numbers = array_map(fn ($c) => $c instanceof Car ? $c->entry_number : $c, $cars);

        return app(VoteService::class)->submit($participant->fresh('event'), $numbers, $source, $admin, (string) Str::uuid());
    }
}
