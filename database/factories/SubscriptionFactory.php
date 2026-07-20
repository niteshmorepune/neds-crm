<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Subscription',
            'vendor' => fake()->company(),
            'cost' => fake()->numberBetween(50000, 500000),
            'billing_cycle' => RecurringFrequency::Monthly->value,
            'renewal_date' => now()->addDays(fake()->numberBetween(1, 60))->toDateString(),
            'reminder_days_before' => 7,
            'notes' => null,
            'is_active' => true,
            'reminder_sent_for' => null,
        ];
    }
}
