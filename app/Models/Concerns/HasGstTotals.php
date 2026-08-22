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
            (bool) $this->is_gst_exempt,
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

    /**
     * Per-HSN/SAC GST summary for the printed document (grouped by SAC code +
     * GST rate, matching how a real GST invoice groups line items) --
     * re-runs GstCalculator's own per-line math against the already-loaded
     * `items` relation rather than querying again, so it can never disagree
     * with the document's own stored totals and never doubles the query
     * this incurs during PDF/email rendering.
     *
     * @return list<array{sac_code: string, gst_rate: float, taxable: int, cgst: int, sgst: int, igst: int}>
     */
    public function hsnSummary(): array
    {
        $items = $this->items;

        $lines = $items->map(fn ($item) => [
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
            (bool) $this->is_gst_exempt,
        );

        $summary = [];
        foreach ($items->values() as $i => $item) {
            $line = $gst['lines'][$i];
            $key = ($item->sac_code ?? '—').'|'.$item->gst_rate;

            $summary[$key] ??= [
                'sac_code' => $item->sac_code ?? '—',
                'gst_rate' => (float) $item->gst_rate,
                'taxable' => 0,
                'cgst' => 0,
                'sgst' => 0,
                'igst' => 0,
            ];

            $summary[$key]['taxable'] += $line['taxable'];
            $summary[$key]['cgst'] += $line['cgst'];
            $summary[$key]['sgst'] += $line['sgst'];
            $summary[$key]['igst'] += $line['igst'];
        }

        return array_values($summary);
    }
}
