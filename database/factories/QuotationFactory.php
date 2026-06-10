<?php

namespace Database\Factories;

use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quotation>
 */
class QuotationFactory extends Factory
{
    protected $model = Quotation::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => QuotationStatus::Draft,
            'place_of_supply_state_code' => '27',
            'is_intra_state' => true,
            'discount' => 0,
            'terms' => '50% advance, balance on delivery.',
            'validity_date' => now()->addDays(15),
        ];
    }

    public function status(QuotationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
