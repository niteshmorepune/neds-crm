<?php

namespace Database\Factories;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationItem>
 */
class QuotationItemFactory extends Factory
{
    protected $model = QuotationItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $rate = fake()->numberBetween(100000, 5000000); // paise

        return [
            'quotation_id' => Quotation::factory(),
            'description' => fake()->sentence(3),
            'sac_code' => (string) fake()->numberBetween(998311, 998399),
            'quantity' => $quantity,
            'rate' => $rate,
            'gst_rate' => 18,
            'amount' => $quantity * $rate,
            'sort_order' => 0,
        ];
    }
}
