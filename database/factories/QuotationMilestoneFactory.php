<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationMilestone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationMilestone>
 */
class QuotationMilestoneFactory extends Factory
{
    protected $model = QuotationMilestone::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'title' => fake()->randomElement(['Advance', 'On UAT', 'On go-live']),
            'percentage' => 50,
            'amount' => 0,
            'sort_order' => 0,
        ];
    }
}
