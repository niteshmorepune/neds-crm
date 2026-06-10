<?php

namespace Database\Factories;

use App\Enums\RecurringFrequency;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringInvoice>
 */
class RecurringInvoiceFactory extends Factory
{
    protected $model = RecurringInvoice::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'frequency' => RecurringFrequency::Monthly,
            'day_of_month' => 1,
            'start_date' => now()->startOfMonth(),
            'end_date' => null,
            'next_run_on' => now()->startOfMonth(),
            'is_active' => true,
            'discount' => 0,
        ];
    }

    public function due(): static
    {
        return $this->state(fn () => ['next_run_on' => now()->subDay()->toDateString()]);
    }
}
