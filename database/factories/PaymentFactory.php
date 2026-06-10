<?php

namespace Database\Factories;

use App\Enums\PaymentMode;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'paid_on' => now(),
            'mode' => PaymentMode::Upi,
            'reference' => fake()->bothify('UPI-####??'),
            'amount' => fake()->numberBetween(100000, 5000000),
        ];
    }
}
