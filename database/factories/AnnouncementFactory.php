<?php

namespace Database\Factories;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'body' => fake()->paragraph(),
            'audience' => AnnouncementAudience::Both->value,
            'is_pinned' => false,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'created_by' => null,
        ];
    }
}
