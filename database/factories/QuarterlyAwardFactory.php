<?php

namespace Database\Factories;

use App\Enums\AwardStatus;
use App\Enums\UserRole;
use App\Models\QuarterlyAward;
use App\Models\User;
use App\Support\FinancialQuarter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuarterlyAward>
 */
class QuarterlyAwardFactory extends Factory
{
    protected $model = QuarterlyAward::class;

    public function definition(): array
    {
        return [
            'financial_year' => FinancialQuarter::financialYear(now()),
            'quarter' => FinancialQuarter::quarterOf(now()),
            'department' => UserRole::Sales->value,
            'user_id' => User::factory(),
            'score' => $this->faker->numberBetween(60, 100),
            'citation' => $this->faker->sentence(),
            'status' => AwardStatus::Pending,
        ];
    }

    public function companyWide(): static
    {
        return $this->state(['department' => QuarterlyAward::COMPANY_WIDE]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => AwardStatus::Approved,
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }
}
