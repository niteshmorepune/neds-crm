<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator. Idempotent (keyed on email) and safe to
     * run in production — it never overwrites an existing admin's password.
     */
    public function run(): void
    {
        $email = 'niranjan.enterprisespune@gmail.com';

        User::firstOrCreate(
            ['email' => $email],
            [
                'name' => 'NEDS Admin',
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
                'password' => Hash::make('password'), // change after first login
            ],
        );
    }
}
