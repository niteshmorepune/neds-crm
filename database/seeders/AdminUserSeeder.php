<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator. Idempotent (keyed on email) and safe to
     * run in production — it never overwrites an existing admin's password.
     * Generates a random password each time it actually creates the user
     * (never a fixed, guessable default sitting in the repo) and prints it
     * once so it can be retrieved from the seed output — use "Forgot
     * password?" on /login afterward for a real, memorable one.
     */
    public function run(): void
    {
        $email = 'niranjan.enterprisespune@gmail.com';

        if (User::where('email', $email)->exists()) {
            return;
        }

        $password = Str::random(20);

        User::create([
            'name' => 'NEDS Admin',
            'email' => $email,
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
        ]);

        $this->command?->warn("Admin user created for {$email} — one-time password: {$password}");
    }
}
