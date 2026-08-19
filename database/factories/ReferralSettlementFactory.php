<?php

namespace Database\Factories;

use App\Enums\PartnerCollectionMode;
use App\Enums\SettlementAmountSource;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferralSettlement>
 */
class ReferralSettlementFactory extends Factory
{
    protected $model = ReferralSettlement::class;

    public function definition(): array
    {
        $billedAmount = fake()->numberBetween(100000, 1000000);
        $rate = 20;

        return [
            'customer_id' => Customer::factory(),
            'partner_id' => Partner::factory(),
            'recurring_invoice_id' => RecurringInvoice::factory(),
            'period_start' => now()->startOfMonth(),
            'flow_mode' => PartnerCollectionMode::NedsCollects,
            'billed_amount' => $billedAmount,
            'share_rate' => $rate,
            'share_amount' => (int) round($billedAmount * $rate / 100),
            'amount_source' => SettlementAmountSource::Invoice,
            'entered_by' => null,
            'finalized_at' => now(),
            'settled_at' => null,
            'settled_by' => null,
        ];
    }

    public function partnerCollects(): static
    {
        return $this->state(fn () => ['flow_mode' => PartnerCollectionMode::PartnerCollects, 'amount_source' => SettlementAmountSource::Manual]);
    }

    public function settled(): static
    {
        return $this->state(fn () => ['settled_at' => now()]);
    }
}
