<?php

namespace Tests\Feature\Admin;

use App\Enums\EventStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class CarAdminTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->makeEvent();

        $this->get(route('admin.cars.index'))->assertRedirect(route('admin.login'));
    }

    public function test_contestant_user_gets_403(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event);
        $car = $participant->cars[0];

        $this->actingAs($participant->user)->get(route('admin.cars.index'))->assertForbidden();
        $this->actingAs($participant->user)->get(route('admin.cars.create'))->assertForbidden();
        $this->actingAs($participant->user)->get(route('admin.cars.edit', $car))->assertForbidden();
    }

    public function test_admin_registers_car_for_existing_contestant(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $participant = $this->makeContestant($event, 1, $category);

        $response = $this->actingAs($admin)->post(route('admin.cars.store'), [
            'participant_id' => $participant->id,
            'category_id' => $category->id,
            'description' => '1965 Mustang',
        ]);

        $response->assertRedirect(route('admin.contestants.show', $participant));
        $this->assertCount(2, $participant->fresh()->cars);
    }

    public function test_car_structural_edit_rejected_when_voting_open(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $other = $this->makeCategory(2, 'Classic');
        $participant = $this->makeContestant($event, 1, $category);
        $car = $participant->cars[0];
        $this->forceStatus($event, EventStatus::VotingOpen);

        $response = $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'category_id' => $other->id,
            'description' => $car->description,
        ]);

        $response->assertSessionHasErrors('registration');
        $this->assertSame($category->id, $car->fresh()->category_id);
    }

    public function test_car_description_can_be_edited_when_voting_open(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $participant = $this->makeContestant($event, 1, $category);
        $car = $participant->cars[0];
        $this->forceStatus($event, EventStatus::VotingOpen);

        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'description' => 'Updated description',
        ])->assertRedirect(route('admin.cars.edit', $car));

        $this->assertSame('Updated description', $car->fresh()->description);
    }

    public function test_delete_blocked_once_voting_opens(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $participant = $this->makeContestant($event, 1, $category);
        $car = $participant->cars[0];
        $this->forceStatus($event, EventStatus::VotingOpen);

        $this->actingAs($admin)->delete(route('admin.cars.destroy', $car))->assertForbidden();
        $this->assertNotNull($car->fresh());
    }

    public function test_photo_upload_stores_webp(): void
    {
        Storage::fake('public');
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $participant = $this->makeContestant($event, 1, $category);
        $car = $participant->cars[0];

        $file = UploadedFile::fake()->image('car.jpg', 2000, 1500);

        $this->actingAs($admin)->put(route('admin.cars.update', $car), [
            'description' => $car->description,
            'photo' => $file,
        ])->assertRedirect(route('admin.cars.edit', $car));

        $car->refresh();
        $this->assertNotNull($car->photo_path);
        $this->assertStringEndsWith('.webp', $car->photo_path);
        $this->assertNotNull($car->thumb_path);
        Storage::disk('public')->assertExists($car->photo_path);
        Storage::disk('public')->assertExists($car->thumb_path);
    }
}
