<?php

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ConvertQuotationToInvoice
{
    public function __construct(private readonly InvoiceNumberGenerator $numbers) {}

    private function financialYear(Carbon $date): string
    {
        return $this->numbers->financialYear($date);
    }

    /**
     * Create an Invoice (with item snapshot) from an accepted quotation.
     * Idempotent guard: a quotation can only be converted once.
     */
    public function handle(Quotation $quotation, ?Carbon $issueDate = null, ?Carbon $dueDate = null): Invoice
    {
        if ($quotation->status !== QuotationStatus::Accepted) {
            throw new RuntimeException('Only an accepted quotation can be converted to an invoice.');
        }

        if ($quotation->invoice()->exists()) {
            throw new RuntimeException('This quotation has already been invoiced.');
        }

        $issueDate ??= Carbon::now();
        $dueDate ??= $issueDate->copy()->addDays(15);

        return DB::transaction(function () use ($quotation, $issueDate, $dueDate) {
            $invoice = Invoice::create([
                'invoice_number' => null,
                'financial_year' => $this->financialYear($issueDate),
                'customer_id' => $quotation->customer_id,
                'deal_id' => $quotation->deal_id,
                'quotation_id' => $quotation->id,
                'status' => InvoiceStatus::Sent->value,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'place_of_supply_state_code' => $quotation->place_of_supply_state_code,
                'discount' => $quotation->discount,
                'is_gst_exempt' => $quotation->is_gst_exempt,
            ]);

            foreach ($quotation->items as $item) {
                $invoice->items()->create([
                    'description' => $item->description,
                    'sac_code' => $item->sac_code,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'gst_rate' => $item->gst_rate,
                    'amount' => $item->amount,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $invoice->refresh()->recalculateTotals();

            return $invoice;
        });
    }
}
