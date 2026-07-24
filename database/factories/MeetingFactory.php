<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'meetable_type' => null,
            'meetable_id' => null,
            'google_event_id' => fake()->unique()->uuid(),
            'title' => fake()->sentence(3),
            'occurred_at' => now(),
            'duration_minutes' => fake()->numberBetween(15, 60),
            'attendees' => [fake()->name(), fake()->name()],
            'drive_recording_url' => null,
            'drive_transcript_url' => null,
            'raw_transcript' => null,
        ];
    }
}
