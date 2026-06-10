<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = fake()->numberBetween(1, 5);
        $rate = fake()->numberBetween(100000, 5000000);

        return [
            'invoice_id' => Invoice::factory(),
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
