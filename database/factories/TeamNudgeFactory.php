<?php

namespace Database\Factories;

use App\Enums\NudgeRecurrence;
use App\Models\TeamNudge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamNudge>
 */
class TeamNudgeFactory extends Factory
{
    protected $model = TeamNudge::class;

    public function definition(): array
    {
        return [
            'title' => fake()->unique()->sentence(4),
            'description' => fake()->paragraph(),
            'target_role' => null,
            'recurrence' => NudgeRecurrence::OneTime->value,
            'auto_detect_type' => null,
            'due_date' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
