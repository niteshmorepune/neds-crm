<?php

namespace App\Console\Commands;

use App\Enums\PartnerCollectionMode;
use App\Enums\SettlementAmountSource;
use App\Models\Customer;
use App\Models\ReferralSettlement;
use App\Services\ReferralSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Locks the referral settlement share for the month that just ended, for
 * every NedsCollects referred client's recurring services — same
 * idempotency pattern as FinalizePartnerCommissions/FinalizeIncentives
 * (update-or-create keyed via whereDate(), not a naive updateOrCreate()).
 *
 * PartnerCollects clients are deliberately NOT touched here — there is no
 * NEDS invoice to sum for them (the owner's explicit call), so their
 * billed_amount only ever comes from staff manually entering it via
 * ReferralSettlementService::recordManualBilling().
 */
class FinalizeReferralSettlements extends Command
{
    protected $signature = 'app:finalize-referral-settlements
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Lock the referral settlement share for each NedsCollects referred client\'s recurring services for the month that just ended (run on the 1st of each month).';

    public function handle(ReferralSettlementService $service): int
    {
        $monthArg = $this->option('month');
        $monthStart = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();

        $customers = Customer::query()
            ->whereNotNull('referring_partner_id')
            ->whereNotNull('referral_share_rate')
            ->where('referral_share_rate', '>', 0)
            ->where(function ($q) {
                $q->whereNull('partner_collection_mode')
                    ->orWhere('partner_collection_mode', PartnerCollectionMode::NedsCollects->value);
            })
            ->with('recurringInvoices.invoices')
            ->get();

        $finalized = 0;

        foreach ($customers as $customer) {
            foreach ($customer->nonOrphanedRecurringInvoices() as $template) {
                $billedAmount = $service->billedAmountFromInvoices($template, $monthStart);

                if ($billedAmount <= 0) {
                    continue; // nothing billed this template this month — nothing to settle.
                }

                $rate = (float) $customer->referral_share_rate;
                $values = [
                    'customer_id' => $customer->id,
                    'partner_id' => $customer->referring_partner_id,
                    'flow_mode' => PartnerCollectionMode::NedsCollects,
                    'billed_amount' => $billedAmount,
                    'share_rate' => $rate,
                    'share_amount' => (int) round($billedAmount * $rate / 100),
                    'amount_source' => SettlementAmountSource::Invoice,
                    'finalized_at' => now(),
                ];

                $existing = ReferralSettlement::where('recurring_invoice_id', $template->id)
                    ->whereDate('period_start', $monthStart)
                    ->first();

                if ($existing) {
                    $existing->update($values);
                } else {
                    ReferralSettlement::create(['recurring_invoice_id' => $template->id, 'period_start' => $monthStart] + $values);
                }

                $finalized++;
            }
        }

        $this->info("Finalized {$finalized} referral settlement(s) for {$monthStart->format('F Y')}.");

        return self::SUCCESS;
    }
}
