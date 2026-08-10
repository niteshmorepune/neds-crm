<?php

namespace Database\Factories;

use App\Models\Partner;
use App\Models\PartnerCommissionStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCommissionStatement>
 */
class PartnerCommissionStatementFactory extends Factory
{
    protected $model = PartnerCommissionStatement::class;

    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'period_start' => now()->startOfMonth(),
            'referred_value' => fake()->numberBetween(0, 10_000_000),
            'commission_rate' => 10,
            'commission_amount' => fake()->numberBetween(0, 1_000_000),
            'finalized_at' => now(),
            'paid_at' => null,
            'paid_by' => null,
        ];
    }
}
