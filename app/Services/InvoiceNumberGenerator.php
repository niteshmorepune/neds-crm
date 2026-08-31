<?php

namespace App\Services;

use App\Models\InvoiceNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates invoice numbers matching the team's real Hitech numbering, with
 * no gaps and no duplicates under concurrency (the per-FY-per-type counter
 * row is locked FOR UPDATE inside a transaction so concurrent requests
 * serialise). Three independent sequences per financial year:
 *  - 'export'   -- customer is overseas (out of India), zero-rated. Wins
 *                  over 'non_gst' when a client is both overseas AND
 *                  flagged GST-exempt (confirmed with the owner 2026-08-31).
 *  - 'non_gst'  -- domestic but the invoice itself is GST-exempt
 *                  (Invoice::is_gst_exempt), whatever the reason.
 *  - 'domestic' -- everything else.
 *
 * Format has two eras for 'domestic'/'export', confirmed with the owner
 * 2026-08-30:
 *  - FY 2026-27 and earlier (matching Hitech's own numbering as of that
 *    date):   domestic "26/27-040"   export "26/27-IN022"
 *  - FY 2027-28 onward (adds the NEDS/ prefix):
 *              domestic "NEDS/27-28/001"   export "NEDS/27-28/IN001"
 * 'non_gst' has no such era -- confirmed 2026-08-31 to start immediately
 * (this FY) as "INV/26-27/001", "INV/27-28/001", etc., every year.
 *
 * Financial year runs April–March, e.g. 2026-27.
 */
class InvoiceNumberGenerator
{
    /** The first financial-year start-year the NEDS/ prefix applies to 'domestic'/'export'. */
    private const NEDS_PREFIX_FROM_YEAR = 2027;

    public function financialYear(CarbonInterface $date): string
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
    }

    private function sequenceType(bool $isOverseas, bool $isGstExempt): string
    {
        return match (true) {
            $isOverseas => 'export',
            $isGstExempt => 'non_gst',
            default => 'domestic',
        };
    }

    /** Short "27-28"-style year pair used in the display format (not the stored financial_year column). */
    private function shortYearPair(int $startYear): array
    {
        return [substr((string) $startYear, -2), substr((string) ($startYear + 1), -2)];
    }

    private function usesNedsPrefix(int $startYear): bool
    {
        return $startYear >= self::NEDS_PREFIX_FROM_YEAR;
    }

    /** Builds the display number for a given sequence value, era, and type -- no DB access. */
    private function format(int $startYear, string $sequenceType, int $number): string
    {
        [$yy, $yy2] = $this->shortYearPair($startYear);

        if ($sequenceType === 'non_gst') {
            return sprintf('INV/%s-%s/%03d', $yy, $yy2, $number);
        }

        $seqPart = $sequenceType === 'export' ? sprintf('IN%03d', $number) : sprintf('%03d', $number);

        return $this->usesNedsPrefix($startYear)
            ? "NEDS/{$yy}-{$yy2}/{$seqPart}"
            : "{$yy}/{$yy2}-{$seqPart}";
    }

    /**
     * The highest sequence number already used for this FY+type, scanned
     * directly off `invoices.invoice_number` (not the counter row) -- so a
     * manually-logged Hitech number (InvoiceController::store/importStore)
     * is always accounted for even though it never advances the counter
     * itself. See InvoiceNumberGeneratorTest for the production-incident
     * regressions this guards against.
     */
    private function maxUsed(int $startYear, string $sequenceType): int
    {
        [$yy, $yy2] = $this->shortYearPair($startYear);

        if ($sequenceType === 'non_gst') {
            $prefix = "INV/{$yy}-{$yy2}/";

            return DB::table('invoices')
                ->where('invoice_number', 'like', "{$prefix}%")
                ->pluck('invoice_number')
                ->map(fn (string $number) => (int) Str::after($number, $prefix))
                ->max() ?? 0;
        }

        $separator = $this->usesNedsPrefix($startYear) ? '/' : '-';
        $basePattern = $this->usesNedsPrefix($startYear) ? "NEDS/{$yy}-{$yy2}/%" : "{$yy}/{$yy2}-%";

        return DB::table('invoices')
            ->where('invoice_number', 'like', $basePattern)
            ->pluck('invoice_number')
            ->map(function (string $number) use ($separator, $sequenceType) {
                $tail = Str::afterLast($number, $separator);
                $tailIsExport = Str::startsWith($tail, 'IN');

                if ($tailIsExport !== ($sequenceType === 'export')) {
                    return 0; // belongs to a different sequence type -- ignore
                }

                return (int) ($tailIsExport ? substr($tail, 2) : $tail);
            })
            ->max() ?? 0;
    }

    public function generate(?CarbonInterface $issueDate = null, bool $isOverseas = false, bool $isGstExempt = false): string
    {
        $issueDate ??= Carbon::now();
        $fy = $this->financialYear($issueDate);
        $startYear = (int) explode('-', $fy)[0];
        $sequenceType = $this->sequenceType($isOverseas, $isGstExempt);

        // Ensure the row exists before locking (avoids lock-on-missing-row races;
        // the unique constraint makes a duplicate insert safe).
        InvoiceNumberSequence::firstOrCreate(['financial_year' => $fy, 'sequence_type' => $sequenceType]);

        return DB::transaction(function () use ($fy, $sequenceType, $startYear) {
            $sequence = InvoiceNumberSequence::where('financial_year', $fy)
                ->where('sequence_type', $sequenceType)
                ->lockForUpdate()
                ->first();

            $maxUsed = $this->maxUsed($startYear, $sequenceType);

            // Absolute update (not increment()) -- Eloquent's increment() issues a
            // DB-relative "column = column + amount", which would silently ignore
            // the self-heal above and leave the persisted counter lagging behind
            // reality even when the returned number here is correct.
            $next = max($sequence->last_number, $maxUsed) + 1;
            $sequence->update(['last_number' => $next]);

            return $this->format($startYear, $sequenceType, $next);
        });
    }

    /**
     * Read-only preview of the raw sequence number generate() would assign
     * next, for the Invoice numbering settings panel. Never locks or
     * persists anything.
     */
    public function peekNumber(?CarbonInterface $issueDate = null, bool $isOverseas = false, bool $isGstExempt = false): int
    {
        $issueDate ??= Carbon::now();
        $fy = $this->financialYear($issueDate);
        $startYear = (int) explode('-', $fy)[0];
        $sequenceType = $this->sequenceType($isOverseas, $isGstExempt);

        $lastNumber = InvoiceNumberSequence::where('financial_year', $fy)
            ->where('sequence_type', $sequenceType)
            ->value('last_number') ?? 0;

        return max($lastNumber, $this->maxUsed($startYear, $sequenceType)) + 1;
    }

    /** Read-only preview of the formatted display string generate() would return next. */
    public function peek(?CarbonInterface $issueDate = null, bool $isOverseas = false, bool $isGstExempt = false): string
    {
        $issueDate ??= Carbon::now();
        $startYear = (int) explode('-', $this->financialYear($issueDate))[0];
        $sequenceType = $this->sequenceType($isOverseas, $isGstExempt);

        return $this->format($startYear, $sequenceType, $this->peekNumber($issueDate, $isOverseas, $isGstExempt));
    }
}
