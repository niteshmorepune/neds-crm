<?php

namespace Database\Factories;

use App\Models\WeeklyDigest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WeeklyDigest>
 */
class WeeklyDigestFactory extends Factory
{
    protected $model = WeeklyDigest::class;

    public function definition(): array
    {
        return [
            'digest_date' => now()->startOfWeek()->toDateString(),
            'summary' => fake()->sentence(12),
            'pipeline_open_deals_count' => fake()->numberBetween(0, 20),
            'pipeline_open_value' => fake()->numberBetween(0, 50000000),
            'deals_won_count' => fake()->numberBetween(0, 5),
            'deals_lost_count' => fake()->numberBetween(0, 5),
            'mrr_total' => fake()->numberBetween(0, 10000000),
            'recurring_contracts_expiring_count' => fake()->numberBetween(0, 5),
            'cash_expected_this_month' => fake()->numberBetween(0, 10000000),
            'cash_expected_three_months' => fake()->numberBetween(0, 30000000),
            'receivables_total_outstanding' => fake()->numberBetween(0, 20000000),
            'receivables_overdue_ninety_plus_days' => fake()->numberBetween(0, 5000000),
            'client_radar_flagged_count' => fake()->numberBetween(0, 60),
            'client_radar_low_satisfaction_count' => fake()->numberBetween(0, 10),
            'client_radar_overdue_invoice_count' => fake()->numberBetween(0, 10),
        ];
    }
}
