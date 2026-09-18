<?php

namespace Tests\Feature\Import;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_working_admin_non_interactively(): void
    {
        putenv('ADMIN_PASSWORD=correct-horse-battery-staple');

        $this->artisan('app:create-admin', [
            '--name' => 'Head Elf',
            '--username' => 'headelf',
            '--no-interaction' => true,
        ])->assertSuccessful();

        $user = User::query()->where('username', 'headelf')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_admin);
        $this->assertTrue(Hash::check('correct-horse-battery-staple', $user->password));

        putenv('ADMIN_PASSWORD');
    }

    public function test_refuses_short_password(): void
    {
        putenv('ADMIN_PASSWORD=tooshort');

        $this->artisan('app:create-admin', [
            '--name' => 'Head Elf',
            '--username' => 'headelf2',
            '--no-interaction' => true,
        ])->assertFailed();

        $this->assertNull(User::query()->where('username', 'headelf2')->first());

        putenv('ADMIN_PASSWORD');
    }
}
