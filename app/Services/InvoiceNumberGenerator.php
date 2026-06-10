<?php

namespace App\Services;

use App\Models\InvoiceNumberSequence;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

            $sequence->increment('last_number');

            return sprintf('NEDS/%s/%04d', $fy, $sequence->last_number);
        });
    }
}
