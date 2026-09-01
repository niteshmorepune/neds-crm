<?php

namespace App\Console\Commands;

use App\Models\RepWinRateSnapshot;
use App\Services\RepWinRateMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Records each rep's win rate (Won / (Won + Lost) for deals closed in the
 * period) for the month that just ended, broken down by lead source and by
 * the originating lead's score band. Reuses RepWinRateMetrics::calculate()
 * directly -- no aggregation logic is reimplemented here.
 *
 * Measurement only, per this phase's own framing: this command writes data
 * for a future routing-tuning decision to use -- it does NOT wire into
 * LeadAssignmentRule or any other routing/scoring decision. The number is
 * made visible, not made to matter yet.
 *
 * Same idempotency shape as App\Console\Commands\FinalizeIncentives /
 * SnapshotScoreCalibration: keyed lookup via whereDate() (a plain where()
 * on a 'date'-cast column quietly serializes as a full datetime string),
 * explicit find-then-update-or-create so a nullable dimension_value (the
 * 'overall' row) is matched correctly -- MySQL's own unique index can't be
 * relied on to catch a duplicate NULL (same caveat as sales_targets'
 * nullable user_id).
 */
class SnapshotRepWinRates extends Command
{
    protected $signature = 'app:snapshot-rep-win-rates
                            {--month= : Target month in Y-m format (e.g. 2026-06). Defaults to the month that just ended.}';

    protected $description = 'Record each rep\'s win rate for the month that just ended, by lead source and score band (run on the 1st of each month).';

    public function handle(RepWinRateMetrics $metrics): int
    {
        if (! config('services.reports.rep_win_rate_snapshot_enabled', true)) {
            $this->info('Rep win-rate snapshotting is disabled (REP_WIN_RATE_SNAPSHOT_ENABLED=false) -- no-op.');

            return self::SUCCESS;
        }

        $monthArg = $this->option('month');
        $monthStart = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $rows = $metrics->calculate($monthStart, $monthEnd);

        foreach ($rows as $row) {
            $this->upsert($row['user_id'], $monthStart, 'overall', null, $row['overall']);

            foreach ($row['by_source'] as $sourceRow) {
                $this->upsert($row['user_id'], $monthStart, 'source', $sourceRow['dimension_value'], $sourceRow);
            }

            foreach ($row['by_score_band'] as $bandRow) {
                $this->upsert($row['user_id'], $monthStart, 'score_band', $bandRow['dimension_value'], $bandRow);
            }
        }

        $this->info("Snapshotted rep win rates for {$monthStart->format('F Y')} — {$this->pluralize(count($rows), 'rep')}.");

        return self::SUCCESS;
    }

    private function upsert(int $userId, Carbon $monthStart, string $dimension, ?string $value, array $data): void
    {
        $values = [
            'won_count' => $data['won_count'],
            'lost_count' => $data['lost_count'],
            'win_rate' => $data['win_rate'],
        ];

        $query = RepWinRateSnapshot::where('user_id', $userId)
            ->whereDate('period_start', $monthStart)
            ->where('dimension', $dimension);
        $query = $value === null ? $query->whereNull('dimension_value') : $query->where('dimension_value', $value);

        $existing = $query->first();

        if ($existing) {
            $existing->update($values);
        } else {
            RepWinRateSnapshot::create([
                'user_id' => $userId,
                'period_start' => $monthStart,
                'dimension' => $dimension,
                'dimension_value' => $value,
            ] + $values);
        }
    }

    private function pluralize(int $count, string $word): string
    {
        return "{$count} {$word}".($count === 1 ? '' : 's');
    }
}
