<?php

namespace App\Console\Commands;

use App\Models\ScoreCalibrationSnapshot;
use App\Services\ScoreCalibrationMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Records the Score Calibration report's on-demand numbers as a dated
 * snapshot for the month that just ended, so calibration drift is something
 * to actually look back at instead of a manual report someone has to
 * remember to run. Reuses ScoreCalibrationMetrics::scoreCalibration()
 * directly -- no bucketing logic is reimplemented here.
 *
 * Measurement only: this command reads and stores data, it does not feed
 * anything back into ScoreLead, LeadAssignmentRule, or any other
 * scoring/routing decision.
 *
 * Same idempotency shape as App\Console\Commands\FinalizeIncentives: keyed
 * on (period_start, band) via whereDate() (a plain where() on a 'date'-cast
 * column quietly serializes as a full datetime string), so a re-run for the
 * same month recomputes rather than duplicating.
 */
class SnapshotScoreCalibration extends Command
{
    protected $signature = 'app:snapshot-score-calibration
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Record this period\'s Score Calibration numbers as a dated snapshot (run on the 1st of each month).';

    public function handle(ScoreCalibrationMetrics $metrics): int
    {
        if (! config('services.reports.score_calibration_snapshot_enabled', true)) {
            $this->info('Score calibration snapshotting is disabled (SCORE_CALIBRATION_SNAPSHOT_ENABLED=false) -- no-op.');

            return self::SUCCESS;
        }

        $monthArg = $this->option('month');
        $monthStart = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $data = $metrics->scoreCalibration($monthStart, $monthEnd);

        foreach ($data['buckets'] as $bucket) {
            $values = [
                'total' => $bucket['total'],
                'converted' => $bucket['converted'],
                'lost' => $bucket['lost'],
                'conversion_rate' => $bucket['conversion_rate'],
                'avg_days_to_close_converted' => $bucket['avg_days_to_close_converted'],
                'median_days_to_close_converted' => $bucket['median_days_to_close_converted'],
                'avg_days_to_close_lost' => $bucket['avg_days_to_close_lost'],
                'median_days_to_close_lost' => $bucket['median_days_to_close_lost'],
            ];

            $existing = ScoreCalibrationSnapshot::where('band', $bucket['band'])
                ->whereDate('period_start', $monthStart)
                ->first();

            if ($existing) {
                $existing->update($values);
            } else {
                ScoreCalibrationSnapshot::create(['period_start' => $monthStart, 'band' => $bucket['band']] + $values);
            }
        }

        $bandCount = count($data['buckets']);
        $this->info("Snapshotted score calibration for {$monthStart->format('F Y')} — {$data['total']} lead(s) closed across {$bandCount} band(s).");

        return self::SUCCESS;
    }
}
