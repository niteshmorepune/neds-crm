<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->catchPhrase(),
            'customer_id' => Customer::factory(),
            'owner_id' => null,
            'status' => ProjectStatus::Active,
            'start_date' => now(),
            'end_date' => now()->addMonth(),
        ];
    }
}
