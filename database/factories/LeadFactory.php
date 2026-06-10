<?php

namespace Database\Factories;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'company' => fake()->company(),
            'phone' => fake()->numerify('98########'),
            'email' => fake()->unique()->companyEmail(),
            'source' => fake()->randomElement(LeadSource::cases()),
            'service_id' => null,
            'estimated_value' => fake()->numberBetween(50000, 50000000), // paise
            'owner_id' => null,
            'status' => LeadStatus::New,
            'next_follow_up_at' => null,
        ];
    }

    public function ownedBy(int $userId): static
    {
        return $this->state(fn () => ['owner_id' => $userId]);
    }

    public function dueFollowUp(): static
    {
        return $this->state(fn () => ['next_follow_up_at' => now()->subDay()]);
    }
}
