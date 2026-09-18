<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'name' => 'Synthetic Test Show',
            'year' => 2099,
            'votes_per_car' => 5,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Event $event) {
            $event->is_active ??= true;
            $event->status ??= EventStatus::Setup;
        });
    }
}
