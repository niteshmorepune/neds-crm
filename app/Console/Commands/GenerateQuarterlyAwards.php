<?php

namespace App\Console\Commands;

use App\Services\QuarterlyAwardGenerator;
use App\Support\FinancialQuarter;
use Illuminate\Console\Command;

/**
 * Generates (or regenerates) Best Employee of the Quarter candidates —
 * defaults to the FY quarter that just ended, run automatically on quarter
 * close. Idempotent: App\Services\QuarterlyAwardGenerator never touches an
 * already-Approved row, so a re-run (scheduled or manual "Regenerate" from
 * the review page) only refreshes rows still Pending/Rejected.
 */
class GenerateQuarterlyAwards extends Command
{
    protected $signature = 'app:generate-quarterly-awards
                            {--quarter= : Target quarter as FY-Qn, e.g. 2026-27-Q1. Defaults to the quarter that just ended.}';

    protected $description = 'Generate AI-suggested Best Employee of the Quarter candidates for Admin/Manager review.';

    public function handle(QuarterlyAwardGenerator $generator): int
    {
        $quarterArg = $this->option('quarter');

        if ($quarterArg) {
            if (! preg_match('/^(\d{4}-\d{2})-Q([1-4])$/', $quarterArg, $match)) {
                $this->error('Invalid --quarter format. Expected FY-Qn, e.g. 2026-27-Q1.');

                return self::FAILURE;
            }
            $financialYear = $match[1];
            $quarter = (int) $match[2];
        } else {
            ['financial_year' => $financialYear, 'quarter' => $quarter] = FinancialQuarter::previous();
        }

        $awards = $generator->generate($financialYear, $quarter);

        $this->info("Generated/refreshed {$awards->count()} award(s) for Q{$quarter} FY{$financialYear}.");

        return self::SUCCESS;
    }
}
