<?php

namespace Database\Factories;

use App\Models\IncentiveStatement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncentiveStatement>
 */
class IncentiveStatementFactory extends Factory
{
    protected $model = IncentiveStatement::class;

    public function definition(): array
    {
        $salesValue = fake()->numberBetween(1000000, 20000000); // paise

        return [
            'user_id' => User::factory(),
            'period_start' => now()->startOfMonth(),
            'sales_value' => $salesValue,
            'individual_incentive' => (int) round($salesValue * 0.1),
            'team_bonus_eligible' => false,
            'team_bonus_share' => 0,
            'total_incentive' => (int) round($salesValue * 0.1),
            'finalized_at' => now(),
        ];
    }
}
