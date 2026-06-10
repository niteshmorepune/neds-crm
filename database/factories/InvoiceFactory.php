<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $issue = Carbon::now();
        $generator = app(InvoiceNumberGenerator::class);

        return [
            'invoice_number' => $generator->generate($issue),
            'financial_year' => $generator->financialYear($issue),
            'customer_id' => Customer::factory(),
            'status' => InvoiceStatus::Sent,
            'issue_date' => $issue,
            'due_date' => $issue->copy()->addDays(15),
            'place_of_supply_state_code' => '27',
            'is_intra_state' => true,
            'discount' => 0,
            'total' => 0,
            'amount_paid' => 0,
        ];
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
