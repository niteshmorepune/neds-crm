<?php

namespace App\Services;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\ScoreCalibrationSnapshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Score Calibration report — is the 0-100 AI score actually predictive of
 * outcome? Extracted from ReportMetrics (2026-09-01) as its own service so
 * this report's own future changes (e.g. scheduled trend snapshots) don't
 * keep colliding with the other Phase 2 reports' methods in one shared file
 * — three straight PRs landing the same day all touched ReportMetrics.php.
 * Pure move, no behavior change.
 */
class ScoreCalibrationMetrics
{
    /**
     * Buckets closed Leads (Converted or Lost) by their ai_score into the
     * same Cold/Warm/Hot bands already shown on every lead's score badge
     * (see Lead::scoreBandFor()), with conversion rate and time-to-close per
     * bucket. ai_score itself is the "final snapshot" -- there's no separate
     * column, but LeadObserver guarantees a re-score on the actual
     * transition into a terminal status, so it's never stale by the time a
     * lead closes.
     *
     * Scoped by when the lead actually CLOSED (converted_at / lost_at), not
     * created_at -- matches the Loss Reason report's same choice for Deals,
     * and is what makes a rolling monthly re-run meaningful ("what closed
     * this month"), not "what was captured this month."
     *
     * @return array{total: int, buckets: list<array>}
     */
    public function scoreCalibration(Carbon $from, Carbon $to): array
    {
        $leads = Lead::query()
            ->where(function ($query) use ($from, $to) {
                $query->where(fn ($q) => $q->where('status', LeadStatus::Converted)->whereBetween('converted_at', [$from, $to]))
                    ->orWhere(fn ($q) => $q->where('status', LeadStatus::Lost)->whereBetween('lost_at', [$from, $to]));
            })
            ->get();

        $bandOrder = ['hot' => 0, 'warm' => 1, 'cold' => 2, 'no_score' => 3];

        $byBand = $leads->groupBy(fn (Lead $lead) => Lead::scoreBandFor($lead->ai_score) ?? 'no_score');

        // Every band always appears, even with zero leads -- so "Hot: 0" reads
        // as a real answer (no hot leads closed this period) rather than a
        // silently missing row a manager might mistake for a report bug.
        $buckets = collect(array_keys($bandOrder))
            ->map(fn (string $band) => $this->scoreBandRow($band, $byBand->get($band, collect())))
            ->sortBy(fn (array $row) => $bandOrder[$row['band']])
            ->values()
            ->all();

        return [
            'total' => $leads->count(),
            'buckets' => $buckets,
        ];
    }

    /**
     * @param  Collection<int, Lead>  $leads  Every closed lead already in this one score band.
     * @return array{band: string, label: string, total: int, converted: int, lost: int, conversion_rate: int, avg_days_to_close_converted: ?int, median_days_to_close_converted: ?int, avg_days_to_close_lost: ?int, median_days_to_close_lost: ?int}
     */
    private function scoreBandRow(string $band, Collection $leads): array
    {
        $total = $leads->count();
        $converted = $leads->where('status', LeadStatus::Converted);
        $lost = $leads->where('status', LeadStatus::Lost);

        return [
            'band' => $band,
            'label' => Lead::scoreBandLabel($band === 'no_score' ? null : $band),
            'total' => $total,
            'converted' => $converted->count(),
            'lost' => $lost->count(),
            'conversion_rate' => $total > 0 ? (int) round($converted->count() / $total * 100) : 0,
            'avg_days_to_close_converted' => $this->avgDaysToClose($converted, 'converted_at'),
            'median_days_to_close_converted' => $this->medianDaysToClose($converted, 'converted_at'),
            'avg_days_to_close_lost' => $this->avgDaysToClose($lost, 'lost_at'),
            'median_days_to_close_lost' => $this->medianDaysToClose($lost, 'lost_at'),
        ];
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function avgDaysToClose(Collection $leads, string $closedAtField): ?int
    {
        $days = $leads->map(fn (Lead $lead) => $lead->created_at->diffInDays($lead->{$closedAtField}));

        return $days->isEmpty() ? null : (int) round($days->avg());
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function medianDaysToClose(Collection $leads, string $closedAtField): ?int
    {
        $days = $leads->map(fn (Lead $lead) => $lead->created_at->diffInDays($lead->{$closedAtField}))->sort()->values();

        if ($days->isEmpty()) {
            return null;
        }

        $count = $days->count();
        $mid = intdiv($count, 2);

        if ($count % 2 === 0) {
            return (int) round(($days->get($mid - 1) + $days->get($mid)) / 2);
        }

        return (int) $days->get($mid);
    }

    /**
     * Trend view over the recorded ScoreCalibrationSnapshot rows (written
     * monthly by App\Console\Commands\SnapshotScoreCalibration) — one row
     * per period, with each band's conversion rate that period, oldest
     * first. Purely reads what was already snapshotted; never recomputes
     * a past period live, and never feeds anything back into scoring.
     *
     * @return list<array{period: string, period_label: string, hot: ?int, warm: ?int, cold: ?int, no_score: ?int}>
     */
    public function trend(int $months = 12): array
    {
        $bandOrder = ['hot', 'warm', 'cold', 'no_score'];

        $byPeriod = ScoreCalibrationSnapshot::query()
            ->orderBy('period_start')
            ->get()
            ->groupBy(fn (ScoreCalibrationSnapshot $s) => $s->period_start->format('Y-m'));

        $periods = $byPeriod->keys()->sort()->values()->slice(-$months)->values();

        return $periods->map(function (string $period) use ($byPeriod, $bandOrder) {
            $snapshotsForPeriod = $byPeriod->get($period, collect());
            $row = [
                'period' => $period,
                'period_label' => Carbon::createFromFormat('Y-m', $period)->format('M Y'),
            ];

            foreach ($bandOrder as $band) {
                $row[$band] = $snapshotsForPeriod->firstWhere('band', $band)?->conversion_rate;
            }

            return $row;
        })->all();
    }
}
