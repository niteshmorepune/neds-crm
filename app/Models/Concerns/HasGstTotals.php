<?php

namespace App\Models\Concerns;

use App\Services\GstCalculator;
use App\Support\IndianNumber;

/**
 * Shared GST money behaviour for Quotation and Invoice (both have line items
 * and the same paise money columns). Recomputes the document totals from its
 * items via GstCalculator and persists the snapshot.
 */
trait HasGstTotals
{
    public function recalculateTotals(): void
    {
        $lines = $this->items()->orderBy('sort_order')->get()
            ->map(fn ($item) => [
                'quantity' => (float) $item->quantity,
                'rate' => (int) $item->rate,
                'gst_rate' => (float) $item->gst_rate,
            ])->all();

        $isOverseas = $this->customer?->isOverseas() ?? false;

        $gst = app(GstCalculator::class)->calculate(
            $lines,
            (int) $this->discount,
            $this->place_of_supply_state_code,
            $isOverseas,
        );

        $this->forceFill([
            'is_intra_state' => $gst['is_intra_state'],
            'subtotal' => $gst['subtotal'],
            'discount' => $gst['discount'],
            'taxable_total' => $gst['taxable_total'],
            'cgst_total' => $gst['cgst_total'],
            'sgst_total' => $gst['sgst_total'],
            'igst_total' => $gst['igst_total'],
            'round_off' => $gst['round_off'],
            'total' => $gst['total'],
        ])->save();
    }

    public function amountInWords(): string
    {
        return IndianNumber::toWords((int) $this->total);
    }
}
