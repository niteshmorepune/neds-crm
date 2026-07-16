<?php

namespace Database\Factories;

use App\Enums\TargetPeriodType;
use App\Models\SalesTarget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesTarget>
 */
class SalesTargetFactory extends Factory
{
    protected $model = SalesTarget::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'period_type' => TargetPeriodType::Month,
            'period_start' => TargetPeriodType::Month->currentPeriodStart(),
            'target_value' => fake()->numberBetween(10000000, 100000000), // paise
            'created_by' => null,
        ];
    }
}
