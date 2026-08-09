<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\FollowUpReminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FollowUpReminder>
 */
class FollowUpReminderFactory extends Factory
{
    protected $model = FollowUpReminder::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'remind_at' => now()->addDay(),
            'next_action' => $this->faker->sentence(4),
            'notified_at' => null,
            'completed_at' => null,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['remind_at' => now()->subMinutes(10)]);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed_at' => now()]);
    }
}
