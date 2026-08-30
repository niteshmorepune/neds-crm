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
 * serialise). Two independent sequences per financial year -- 'domestic'
 * (India-billed) and 'export' (out-of-India-billed) -- since Hitech numbers
 * these separately (e.g. 26/27-040 vs 26/27-IN022).
 *
 * Format has two eras, both confirmed with the owner 2026-08-30:
 *  - FY 2026-27 and earlier (matching Hitech's own numbering as of that
 *    date):   domestic "26/27-040"   export "26/27-IN022"
 *  - FY 2027-28 onward (adds the NEDS/ prefix):
 *              domestic "NEDS/27-28/001"   export "NEDS/27-28/IN001"
 *
 * Financial year runs April–March, e.g. 2026-27.
 */
class InvoiceNumberGenerator
{
    /** The first financial-year start-year the NEDS/ prefix applies from. */
    private const NEDS_PREFIX_FROM_YEAR = 2027;

    public function financialYear(CarbonInterface $date): string
    {
        $year = $date->month >= 4 ? $date->year : $date->year - 1;

        return sprintf('%d-%02d', $year, ($year + 1) % 100);
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
    private function format(int $startYear, bool $isOverseas, int $number): string
    {
        [$yy, $yy2] = $this->shortYearPair($startYear);
        $seqPart = $isOverseas ? sprintf('IN%03d', $number) : sprintf('%03d', $number);

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
    private function maxUsed(int $startYear, bool $isOverseas): int
    {
        [$yy, $yy2] = $this->shortYearPair($startYear);
        $separator = $this->usesNedsPrefix($startYear) ? '/' : '-';
        $basePattern = $this->usesNedsPrefix($startYear) ? "NEDS/{$yy}-{$yy2}/%" : "{$yy}/{$yy2}-%";

        return DB::table('invoices')
            ->where('invoice_number', 'like', $basePattern)
            ->pluck('invoice_number')
            ->map(function (string $number) use ($separator, $isOverseas) {
                $tail = Str::afterLast($number, $separator);
                $tailIsExport = Str::startsWith($tail, 'IN');

                if ($tailIsExport !== $isOverseas) {
                    return 0; // belongs to the other sequence type -- ignore
                }

                return (int) ($tailIsExport ? substr($tail, 2) : $tail);
            })
            ->max() ?? 0;
    }

    public function generate(?CarbonInterface $issueDate = null, bool $isOverseas = false): string
    {
        $issueDate ??= Carbon::now();
        $fy = $this->financialYear($issueDate);
        $startYear = (int) explode('-', $fy)[0];
        $sequenceType = $isOverseas ? 'export' : 'domestic';

        // Ensure the row exists before locking (avoids lock-on-missing-row races;
        // the unique constraint makes a duplicate insert safe).
        InvoiceNumberSequence::firstOrCreate(['financial_year' => $fy, 'sequence_type' => $sequenceType]);

        return DB::transaction(function () use ($fy, $sequenceType, $startYear, $isOverseas) {
            $sequence = InvoiceNumberSequence::where('financial_year', $fy)
                ->where('sequence_type', $sequenceType)
                ->lockForUpdate()
                ->first();

            $maxUsed = $this->maxUsed($startYear, $isOverseas);

            // Absolute update (not increment()) -- Eloquent's increment() issues a
            // DB-relative "column = column + amount", which would silently ignore
            // the self-heal above and leave the persisted counter lagging behind
            // reality even when the returned number here is correct.
            $next = max($sequence->last_number, $maxUsed) + 1;
            $sequence->update(['last_number' => $next]);

            return $this->format($startYear, $isOverseas, $next);
        });
    }

    /**
     * Read-only preview of the raw sequence number generate() would assign
     * next, for the Invoice numbering settings panel. Never locks or
     * persists anything.
     */
    public function peekNumber(?CarbonInterface $issueDate = null, bool $isOverseas = false): int
    {
        $issueDate ??= Carbon::now();
        $fy = $this->financialYear($issueDate);
        $startYear = (int) explode('-', $fy)[0];
        $sequenceType = $isOverseas ? 'export' : 'domestic';

        $lastNumber = InvoiceNumberSequence::where('financial_year', $fy)
            ->where('sequence_type', $sequenceType)
            ->value('last_number') ?? 0;

        return max($lastNumber, $this->maxUsed($startYear, $isOverseas)) + 1;
    }

    /** Read-only preview of the formatted display string generate() would return next. */
    public function peek(?CarbonInterface $issueDate = null, bool $isOverseas = false): string
    {
        $issueDate ??= Carbon::now();
        $startYear = (int) explode('-', $this->financialYear($issueDate))[0];

        return $this->format($startYear, $isOverseas, $this->peekNumber($issueDate, $isOverseas));
    }
}
