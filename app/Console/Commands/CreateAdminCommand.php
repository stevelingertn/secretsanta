<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Creates (or resets the password of) an admin user. Admins sign in with a
 * username + password; contestants never do. The password is never echoed.
 */
class CreateAdminCommand extends Command
{
    public const MIN_PASSWORD_LENGTH = 12;

    protected $signature = 'app:create-admin {--name=} {--username=}';

    protected $description = 'Create an admin user, or reset an existing admin\'s password.';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Display name');
        $username = $this->option('username') ?: $this->ask('Username');
        $username = trim((string) $username);

        if ($name === null || trim((string) $name) === '' || $username === '') {
            $this->error('Display name and username are required.');

            return self::FAILURE;
        }

        $existing = User::query()->where('username', $username)->first();
        if ($existing && ! $existing->is_admin) {
            $this->error("\"{$username}\" already exists and is not an admin.");

            return self::FAILURE;
        }

        if ($existing) {
            $confirmed = ! $this->input->isInteractive()
                ? true
                : $this->confirm("Admin \"{$username}\" already exists. Reset their password?");
            if (! $confirmed) {
                $this->info('No changes made.');

                return self::SUCCESS;
            }
        }

        $password = $this->readPassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $user = $existing ?? new User;
        $user->name = trim((string) $name);
        $user->username = $username;
        $user->password = $password; // model casts 'password' => 'hashed'
        $user->is_admin = true;
        $user->save();

        $this->info($existing ? "Password reset for \"{$username}\"." : "Admin \"{$username}\" created.");

        return self::SUCCESS;
    }

    /** Reads and confirms a password, hidden on the terminal. Returns null (with an error printed) if invalid. */
    private function readPassword(): ?string
    {
        if (! $this->input->isInteractive()) {
            $password = (string) env('ADMIN_PASSWORD');
            if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
                $this->error('ADMIN_PASSWORD must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

                return null;
            }

            return $password;
        }

        $password = (string) $this->secret('Password (min '.self::MIN_PASSWORD_LENGTH.' characters)');
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            $this->error('Password must be at least '.self::MIN_PASSWORD_LENGTH.' characters.');

            return null;
        }

        $confirm = (string) $this->secret('Confirm password');
        if (! hash_equals($password, $confirm)) {
            $this->error('Passwords did not match.');

            return null;
        }

        return $password;
    }
}
