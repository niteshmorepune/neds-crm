<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\LeadAssignmentRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadAssignmentRule>
 */
class LeadAssignmentRuleFactory extends Factory
{
    protected $model = LeadAssignmentRule::class;

    public function definition(): array
    {
        return [
            'utm_campaign' => fake()->unique()->words(3, true),
            'service_id' => null,
            'assigned_user_id' => User::factory()->state(['role' => UserRole::Sales->value, 'is_active' => true]),
            'active' => true,
        ];
    }

    public function forService(int $serviceId): static
    {
        return $this->state(fn () => ['utm_campaign' => null, 'service_id' => $serviceId]);
    }

    public function vaPaid(): static
    {
        return $this->state(fn () => ['utm_campaign' => null, 'service_id' => null, 'va_paid' => true]);
    }
}
