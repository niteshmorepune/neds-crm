<?php

namespace App\Services;

/**
 * GST engine for quotations and invoices. All money is integer paise.
 *
 * Rules (CLAUDE.md): NEDS is in Maharashtra (state 27). If the place of supply
 * is also 27 the tax is split CGST + SGST (half each); otherwise it is IGST at
 * the full rate. Default rate 18%, editable per line.
 *
 * Approach: a document-level discount is distributed across lines in proportion
 * to each line's amount (remainder assigned to the last line). Tax is computed
 * and rounded per line, then summed. The final payable is rounded to the
 * nearest rupee with a signed round_off adjustment.
 */
class GstCalculator
{
    /**
     * @param  array<int, array{quantity: float|int, rate: int, gst_rate: float|int}>  $lines
     * @return array{
     *   is_intra_state: bool, subtotal: int, discount: int, taxable_total: int,
     *   cgst_total: int, sgst_total: int, igst_total: int, round_off: int, total: int,
     *   lines: array<int, array{amount:int, discount:int, taxable:int, cgst:int, sgst:int, igst:int}>
     * }
     */
    public function calculate(array $lines, int $discount = 0, ?string $placeOfSupplyStateCode = null): array
    {
        $companyState = (string) config('india.company_state_code');
        $isIntraState = $placeOfSupplyStateCode === null || $placeOfSupplyStateCode === $companyState;

        // Line amounts (paise).
        $amounts = array_map(
            fn ($line) => (int) round(((float) $line['quantity']) * (int) $line['rate']),
            $lines,
        );
        $subtotal = array_sum($amounts);
        $discount = max(0, min($discount, $subtotal));

        $result = [
            'is_intra_state' => $isIntraState,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'taxable_total' => 0,
            'cgst_total' => 0,
            'sgst_total' => 0,
            'igst_total' => 0,
            'round_off' => 0,
            'total' => 0,
            'lines' => [],
        ];

        $discountAssigned = 0;
        $lastIndex = count($lines) - 1;

        foreach ($lines as $i => $line) {
            $amount = $amounts[$i];

            // Proportional discount; the last line absorbs the rounding remainder.
            $lineDiscount = $i === $lastIndex
                ? $discount - $discountAssigned
                : ($subtotal > 0 ? (int) round($amount / $subtotal * $discount) : 0);
            $discountAssigned += $lineDiscount;

            $taxable = $amount - $lineDiscount;
            $tax = (int) round($taxable * ((float) $line['gst_rate']) / 100);

            $cgst = $sgst = $igst = 0;
            if ($isIntraState) {
                $cgst = intdiv($tax, 2);
                $sgst = $tax - $cgst; // odd paise to SGST
            } else {
                $igst = $tax;
            }

            $result['taxable_total'] += $taxable;
            $result['cgst_total'] += $cgst;
            $result['sgst_total'] += $sgst;
            $result['igst_total'] += $igst;
            $result['lines'][] = compact('amount') + [
                'discount' => $lineDiscount,
                'taxable' => $taxable,
                'cgst' => $cgst,
                'sgst' => $sgst,
                'igst' => $igst,
            ];
        }

        $preRound = $result['taxable_total'] + $result['cgst_total'] + $result['sgst_total'] + $result['igst_total'];
        $rounded = (int) round($preRound / 100) * 100;
        $result['round_off'] = $rounded - $preRound;
        $result['total'] = $rounded;

        return $result;
    }
}
