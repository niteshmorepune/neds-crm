<?php

namespace App\Services;

use App\Models\InvoiceNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates invoice numbers in the format NEDS/{FY}/{0000} with no gaps and no
 * duplicates under concurrency. The per-FY counter row is locked FOR UPDATE
 * inside a transaction so concurrent requests serialise.
 *
 * Financial year runs April–March, e.g. 2026-27.
 */
class InvoiceNumberGenerator
{
    public function financialYear(CarbonInterface $date): string
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    public function generate(?CarbonInterface $issueDate = null): string
    {
        $fy = $this->financialYear($issueDate ?? Carbon::now());

        // Ensure the row exists before locking (avoids lock-on-missing-row races;
        // the unique constraint makes a duplicate insert safe).
        InvoiceNumberSequence::firstOrCreate(['financial_year' => $fy]);

        return DB::transaction(function () use ($fy) {
            $sequence = InvoiceNumberSequence::where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            // Manually logged / CSV-imported / back-dated invoices (InvoiceController::
            // store, update, importStore) can assign an invoice_number string directly
            // without advancing this counter -- and since financial_year is computed
            // independently from issue_date, a back-dated entry can carry a
            // NEDS/{fy}/... number while its own financial_year column reflects an
            // *earlier* year (e.g. a historical invoice logged with today's default
            // fy prefix but a last-year issue_date). Matching on the number STRING
            // itself, not the financial_year column, means this self-heal can't be
            // fooled by that mismatch -- it always finds the true highest number
            // already claimed for this fy, however it got there.
            $maxUsed = DB::table('invoices')
                ->where('invoice_number', 'like', "NEDS/{$fy}/%")
                ->pluck('invoice_number')
                ->map(fn (string $number) => (int) Str::afterLast($number, '/'))
                ->max() ?? 0;

            // Absolute update (not increment()) -- Eloquent's increment() issues a
            // DB-relative "column = column + amount", which would silently ignore
            // the self-heal above and leave the persisted counter lagging behind
            // reality even when the returned number here is correct.
            $next = max($sequence->last_number, $maxUsed) + 1;
            $sequence->update(['last_number' => $next]);

            return sprintf('NEDS/%s/%04d', $fy, $next);
        });
    }
}
