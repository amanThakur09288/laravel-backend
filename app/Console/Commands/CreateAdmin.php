<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create an admin user, or reset the password of an existing one';

    // Shortest password allowed (it must also have letters and numbers)
    private const MIN_PASSWORD_LENGTH = 6;

    public function handle(): int
    {
        $name = trim((string) $this->ask('Name'));
        $username = Str::lower(trim((string) $this->ask('Username (letters, numbers, . _ -)')));
        $email = Str::lower(trim((string) $this->ask('Email')));
        $password = (string) $this->secret(
            'Password (at least ' . self::MIN_PASSWORD_LENGTH . ' characters, with letters and numbers)'
        );
        $confirmation = (string) $this->secret('Confirm password');

        // If this email already exists, we update that user
        $existing = User::where('email', $email)->first();

        $validator = Validator::make(
            [
                'name' => $name,
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $confirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'username' => [
                    'required',
                    'regex:/^[a-z0-9._-]{3,50}$/',
                    Rule::unique('users', 'username')->ignore($existing?->id),
                ],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'confirmed', Password::min(self::MIN_PASSWORD_LENGTH)->letters()->numbers()],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Creates the admin, or updates it if the email already exists
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'username' => $username,
                'password' => $password,
                'role' => 'admin',
            ]
        );

        // A new password logs the admin out of every open session
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info("Admin saved. Username: {$username}");

        return self::SUCCESS;
    }
}