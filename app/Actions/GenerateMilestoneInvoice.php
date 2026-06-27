<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\QuotationMilestone;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GenerateMilestoneInvoice
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    /**
     * Bill a single milestone as its own invoice. Each original quotation line
     * is prorated by the milestone percentage so per-line GST rates are
     * preserved. Linked to the quotation's deal and back to the milestone.
     */
    public function handle(QuotationMilestone $milestone, ?Carbon $issueDate = null): Invoice
    {
        if ($milestone->isBilled()) {
            throw new RuntimeException('This milestone has already been invoiced.');
        }

        $quotation = $milestone->quotation;
        $issueDate ??= Carbon::now();
        $pct = (float) $milestone->percentage;

        return DB::transaction(function () use ($milestone, $quotation, $issueDate, $pct) {
            $invoice = Invoice::create([
                'invoice_number' => null,
                'financial_year' => $this->numbers->financialYear($issueDate),
                'customer_id' => $quotation->customer_id,
                'deal_id' => $quotation->deal_id,
                'quotation_id' => $quotation->id,
                'status' => InvoiceStatus::Sent->value,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => ($milestone->due_date ?? $issueDate->copy()->addDays(15))->toDateString(),
                'place_of_supply_state_code' => $quotation->place_of_supply_state_code,
                'discount' => 0,
            ]);

            foreach ($quotation->items as $item) {
                $share = (int) round(((int) $item->amount) * $pct / 100);
                $invoice->items()->create([
                    'description' => "{$item->description} — {$milestone->title} ({$pct}%)",
                    'sac_code' => $item->sac_code,
                    'quantity' => 1,
                    'rate' => $share,
                    'gst_rate' => $item->gst_rate,
                    'amount' => $share,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $invoice->refresh()->recalculateTotals();

            $milestone->update(['invoice_id' => $invoice->id]);

            return $invoice;
        });
    }
}
