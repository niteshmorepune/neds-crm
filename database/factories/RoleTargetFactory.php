<?php

namespace Database\Factories;

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Models\RoleTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoleTarget>
 */
class RoleTargetFactory extends Factory
{
    protected $model = RoleTarget::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'metric' => TargetMetric::TasksCompleted,
            'period_type' => TargetPeriodType::Month,
            'period_start' => TargetPeriodType::Month->currentPeriodStart(),
            'target_value' => fake()->numberBetween(10, 100),
            'created_by' => null,
        ];
    }

    public function forUser(int $userId, TargetMetric $metric): static
    {
        return $this->state(fn () => ['user_id' => $userId, 'metric' => $metric]);
    }
}
