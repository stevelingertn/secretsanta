<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsShow;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use BuildsShow, RefreshDatabase;

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->makeEvent();

        $this->get(route('admin.categories.index'))->assertRedirect(route('admin.login'));
    }

    public function test_contestant_user_gets_403(): void
    {
        $event = $this->makeEvent();
        $participant = $this->makeContestant($event);

        $this->actingAs($participant->user)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($participant->user)->get(route('admin.categories.create'))->assertForbidden();
    }

    public function test_admin_can_create_category_with_explicit_id(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'id' => 501,
            'name' => 'Trucks',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', ['id' => 501, 'name' => 'Trucks']);
    }

    public function test_best_overall_is_rejected(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'id' => 502,
            'name' => 'Best Overall',
        ])->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('categories', ['id' => 502]);
    }

    public function test_delete_blocked_when_referenced(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $this->makeContestant($event, 1, $category);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))
            ->assertSessionHasErrors('category');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_delete_allowed_when_unreferenced(): void
    {
        $event = $this->makeEvent();
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(2, 'Unused');

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_rename_blocked_once_locked_by_history(): void
    {
        $event = $this->makeEvent(\App\Enums\EventStatus::VotingOpen);
        $admin = $this->makeAdmin();
        $category = $this->makeCategory(1, 'Muscle');
        $this->makeContestant($event, 1, $category);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Renamed',
        ])->assertSessionHasErrors('name');

        $this->assertSame('Muscle', $category->fresh()->name);
    }
}
