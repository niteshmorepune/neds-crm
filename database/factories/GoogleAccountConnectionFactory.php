<?php

namespace Database\Factories;

use App\Models\GoogleAccountConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleAccountConnection>
 */
class GoogleAccountConnectionFactory extends Factory
{
    protected $model = GoogleAccountConnection::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'access_token' => 'fake-access-token',
            'refresh_token' => 'fake-refresh-token',
            'expires_at' => now()->addHour(),
            'scopes' => 'https://www.googleapis.com/auth/calendar.readonly https://www.googleapis.com/auth/drive.readonly',
            'google_email' => fake()->safeEmail(),
            'connected_at' => now(),
        ];
    }
}
