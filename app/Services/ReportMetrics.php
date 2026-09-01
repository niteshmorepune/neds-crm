<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Enums\InvoiceStatus;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\CallLog;
use App\Models\DailyReport;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations for the Employee Performance and Revenue reports. Kept separate
 * from the controller so the numbers are unit-testable. All periods are
 * inclusive [from 00:00, to 23:59:59].
 */
class ReportMetrics
{
    /**
     * Per-role weights (must sum to a positive total per role) used by
     * rankedEmployeePerformance() to combine employeePerformance()'s metrics
     * into one composite score. A weight of 0 excludes that metric from both
     * the score and weakest_metric candidacy for that role (e.g. Support/
     * Accounts/Intern don't chase lead conversions). First-cut defaults —
     * plain PHP, adjustable without a schema change.
     */
    private const ROLE_WEIGHTS = [
        'sales' => [
            'tasks_completed' => 0.15, 'on_time_pct' => 0.15, 'calls_made' => 0.20,
            'leads_converted' => 0.30, 'attendance_pct' => 0.10, 'daily_reports' => 0.10,
        ],
        'support' => [
            'tasks_completed' => 0.30, 'on_time_pct' => 0.25, 'calls_made' => 0.10,
            'leads_converted' => 0.0, 'attendance_pct' => 0.15, 'daily_reports' => 0.20,
        ],
        'accounts' => [
            'tasks_completed' => 0.35, 'on_time_pct' => 0.25, 'calls_made' => 0.0,
            'leads_converted' => 0.0, 'attendance_pct' => 0.20, 'daily_reports' => 0.20,
        ],
        'intern' => [
            'tasks_completed' => 0.40, 'on_time_pct' => 0.30, 'calls_made' => 0.0,
            'leads_converted' => 0.0, 'attendance_pct' => 0.20, 'daily_reports' => 0.10,
        ],
        // Telecallers work Lead Generation + Calling only (no Deals), so
        // they don't own leads through to conversion — weighted toward
        // call volume instead, not leads_converted.
        'telecaller' => [
            'tasks_completed' => 0.20, 'on_time_pct' => 0.15, 'calls_made' => 0.35,
            'leads_converted' => 0.0, 'attendance_pct' => 0.15, 'daily_reports' => 0.15,
        ],
    ];

    /**
     * One row per internal user for the period.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function employeePerformance(Carbon $from, Carbon $to): Collection
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // Cap at today so future days in a part-completed month don't dilute %.
        $effectiveTo = $to->copy()->min(now()->endOfDay());
        $workingDays = $this->countWorkingDays($from, $effectiveTo);

        return User::query()->orderBy('name')->get()->map(function (User $user) use ($from, $to, $fromDate, $toDate, $workingDays) {
            // Tasks COMPLETED in the period.
            $completedCount = Task::query()
                ->where('assignee_id', $user->id)
                ->whereBetween('completed_at', [$from, $to])
                ->count();

            // Tasks DUE in the period — denominator for on-time %, so overdue
            // tasks that were never finished count against the employee.
            $tasksDue = Task::query()
                ->where('assignee_id', $user->id)
                ->whereBetween('due_date', [$fromDate, $toDate])
                ->get(['due_date', 'completed_at']);

            $onTimeCount = $tasksDue->filter(fn (Task $t) => $t->completed_at !== null &&
                $t->completed_at->toDateString() <= $t->due_date->toDateString()
            )->count();

            $attendance = Attendance::query()
                ->where('user_id', $user->id)
                ->whereBetween('date', [$fromDate, $toDate])
                ->get(['status']);

            $presentEquivalent = $attendance->reduce(fn (float $c, Attendance $a) => $c + match ($a->status) {
                AttendanceStatus::Present => 1.0,
                AttendanceStatus::HalfDay => 0.5,
                default => 0.0,
            }, 0.0);

            return [
                'user_id' => $user->id,
                'user' => $user->name,
                'role' => $user->role->label(),
                'role_value' => $user->role->value,
                'tasks_completed' => $completedCount,
                'on_time_pct' => $tasksDue->count() > 0 ? (int) round($onTimeCount / $tasksDue->count() * 100) : null,
                'calls_made' => CallLog::query()->where('user_id', $user->id)->whereBetween('called_at', [$from, $to])->count(),
                'leads_converted' => Lead::query()->where('owner_id', $user->id)->whereBetween('converted_at', [$from, $to])->count(),
                // Divide by working days, not attendance record count.
                'attendance_pct' => ($attendance->count() > 0 && $workingDays > 0)
                    ? (int) round(min(100, $presentEquivalent / $workingDays * 100))
                    : null,
                'daily_reports' => DailyReport::query()->where('user_id', $user->id)->whereNotNull('submitted_at')->whereBetween('date', [$fromDate, $toDate])->count(),
            ];
        });
    }

    /**
     * employeePerformance() plus a per-role productivity ranking on top —
     * no new data collected, just percentile ranking + a weighted composite
     * score against role peers. Admin/Manager are excluded from ranking
     * entirely (evaluators, not participants, same distinction the
     * Incentive module makes for eligibility). Within a role group of 2+,
     * each metric gets a 0-100 percentile rank (null values excluded from
     * that metric's ranking rather than counted for or against the
     * person), combined via ROLE_WEIGHTS into one score, sorted to assign a
     * 1-based rank, and the person's single lowest-percentile weighted
     * metric is flagged as weakest_metric (the concrete gap to close).
     * Groups under 2 people get a ranking_note instead of a fabricated
     * "1 of 1" rank.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rankedEmployeePerformance(Carbon $from, Carbon $to): Collection
    {
        $rows = $this->employeePerformance($from, $to);
        $rankableRoles = [UserRole::Sales->value, UserRole::Support->value, UserRole::Accounts->value, UserRole::Intern->value, UserRole::Telecaller->value];

        $scored = $rows->map(function (array $row) use ($rows, $rankableRoles) {
            $unranked = [
                'score' => null, 'rank' => null, 'role_group_size' => null,
                'weakest_metric' => null, 'weakest_percentile' => null, 'ranking_note' => null,
            ];

            if (! in_array($row['role_value'], $rankableRoles, true)) {
                return array_merge($row, $unranked);
            }

            $peers = $rows->where('role_value', $row['role_value'])->values();
            $groupSize = $peers->count();

            if ($groupSize < 2) {
                return array_merge($row, $unranked, [
                    'role_group_size' => $groupSize,
                    'ranking_note' => 'Not enough peers in this role yet to compare.',
                ]);
            }

            $weights = self::ROLE_WEIGHTS[$row['role_value']];
            $percentiles = [];
            foreach ($weights as $metric => $weight) {
                $percentiles[$metric] = $weight > 0 ? $this->percentileRank($peers, $row['user_id'], $metric) : null;
            }

            $weightedSum = 0.0;
            $weightTotal = 0.0;
            $weakestMetric = null;
            $weakestPercentile = null;
            foreach ($weights as $metric => $weight) {
                if ($weight <= 0 || $percentiles[$metric] === null) {
                    continue;
                }
                $weightedSum += $percentiles[$metric] * $weight;
                $weightTotal += $weight;
                if ($weakestPercentile === null || $percentiles[$metric] < $weakestPercentile) {
                    $weakestPercentile = $percentiles[$metric];
                    $weakestMetric = $metric;
                }
            }

            return array_merge($row, [
                'score' => $weightTotal > 0 ? (int) round($weightedSum / $weightTotal) : null,
                'rank' => null, // assigned below, once every row in the group has a score
                'role_group_size' => $groupSize,
                'weakest_metric' => $weakestMetric,
                'weakest_percentile' => $weakestPercentile,
                'ranking_note' => null,
            ]);
        });

        $ranks = [];
        foreach ($scored->whereNotNull('score')->groupBy('role_value') as $group) {
            foreach ($group->sortByDesc('score')->values() as $index => $row) {
                $ranks[$row['user_id']] = $index + 1;
            }
        }

        return $scored->map(function (array $row) use ($ranks) {
            $row['rank'] = $ranks[$row['user_id']] ?? null;

            return $row;
        });
    }

    /**
     * @var list<string>
     */
    private const TREND_METRICS = [
        'tasks_completed', 'on_time_pct', 'calls_made', 'leads_converted',
        'attendance_pct', 'daily_reports', 'score',
    ];

    /**
     * rankedEmployeePerformance() plus a month-over-month delta per metric
     * against the immediately preceding calendar month — pure aggregation,
     * no new data collection or formula (Manager panel doc Tier 2 #12: "the
     * performance report already covers one selected month... needs the
     * month-over-month comparison surfaced directly"). Each row's 'trend'
     * key holds current-minus-previous for every TREND_METRICS entry, null
     * where either side is null (e.g. a metric with no denominator that
     * month, or the person wasn't active/ranked in the prior month at all).
     * Compares against the calendar month immediately before $from,
     * regardless of how long the [$from, $to] window itself spans, since
     * "month-over-month" is the doc's own framing.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function employeePerformanceTrend(Carbon $from, Carbon $to): Collection
    {
        $current = $this->rankedEmployeePerformance($from, $to);

        $priorFrom = $from->copy()->subMonthNoOverflow()->startOfMonth();
        $priorTo = $priorFrom->copy()->endOfMonth();
        $prior = $this->rankedEmployeePerformance($priorFrom, $priorTo)->keyBy('user_id');

        return $current->map(function (array $row) use ($prior) {
            $previous = $prior->get($row['user_id']);

            $row['trend'] = collect(self::TREND_METRICS)->mapWithKeys(function (string $metric) use ($row, $previous) {
                $currentValue = $row[$metric] ?? null;
                $previousValue = $previous[$metric] ?? null;

                $delta = ($currentValue !== null && $previousValue !== null) ? $currentValue - $previousValue : null;

                return [$metric => $delta];
            })->all();

            return $row;
        });
    }

    /**
     * Percentile rank (0-100) of $userId's value for $metric among $peers
     * (average-rank method — ties share the midpoint of their span rather
     * than an arbitrary order). Returns null if the person's own value is
     * null, or fewer than 2 peers have a non-null value for this metric.
     */
    private function percentileRank(Collection $peers, int $userId, string $metric): ?int
    {
        $own = $peers->firstWhere('user_id', $userId)[$metric] ?? null;
        $values = $peers->pluck($metric)->filter(fn ($v) => $v !== null)->values();

        if ($own === null || $values->count() < 2) {
            return null;
        }

        $countBelow = $values->filter(fn ($v) => $v < $own)->count();
        $countEqual = $values->filter(fn ($v) => $v == $own)->count();

        return (int) round(($countBelow + ($countEqual - 1) / 2) / ($values->count() - 1) * 100);
    }

    /**
     * Count Monday–Friday days between two dates, inclusive.
     */
    private function countWorkingDays(Carbon $from, Carbon $to): int
    {
        $days = 0;
        $day = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        while ($day->lte($end)) {
            if ($day->isWeekday()) {
                $days++;
            }
            $day->addDay();
        }

        return $days;
    }

    /**
     * Invoiced revenue for the period, split recurring vs one-time and grouped
     * by month, service line, and client. Draft and cancelled invoices excluded.
     */
    public function revenue(Carbon $from, Carbon $to): array
    {
        $invoices = Invoice::query()
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->with(['deal.service', 'customer'])
            ->get();

        $recurring = (int) $invoices->whereNotNull('recurring_invoice_id')->sum('total');
        $total = (int) $invoices->sum('total');

        $monthly = $invoices
            ->groupBy(fn (Invoice $i) => $i->issue_date->format('Y-m'))
            ->map(fn (Collection $group, string $month) => [
                'month' => $month,
                'recurring' => (int) $group->whereNotNull('recurring_invoice_id')->sum('total'),
                'one_time' => (int) $group->whereNull('recurring_invoice_id')->sum('total'),
                'total' => (int) $group->sum('total'),
            ])
            ->sortKeys()
            ->values()
            ->all();

        $byService = $invoices
            ->groupBy(fn (Invoice $i) => $i->deal?->service?->name ?? 'Unspecified')
            ->map(fn (Collection $group, string $name) => ['name' => $name, 'total' => (int) $group->sum('total')])
            ->sortByDesc('total')
            ->values()
            ->all();

        $byClient = $invoices
            ->groupBy(fn (Invoice $i) => $i->customer_id ?? 0)
            ->map(function (Collection $group) {
                $customer = $group->first()->customer;

                return [
                    'customer_id' => $customer?->id,
                    'name' => $customer?->company_name ?? 'Unknown',
                    'total' => (int) $group->sum('total'),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $total,
            'recurring' => $recurring,
            'one_time' => $total - $recurring,
            'invoice_count' => $invoices->count(),
            'monthly' => $monthly,
            'by_service' => $byService,
            'by_client' => $byClient,
        ];
    }

    /**
     * Lead volume and conversion by acquisition channel, for the period leads
     * were created in. "Converted" means the lead became a client
     * (converted_at set); "won value" sums the linked deal's value only for
     * deals that actually closed Won — a converted-but-still-in-pipeline lead
     * contributes to the conversion rate but not yet to won value.
     */
    public function leadSourcePerformance(Carbon $from, Carbon $to): array
    {
        $leads = Lead::query()
            ->whereBetween('created_at', [$from, $to])
            ->with('convertedDeal')
            ->get();

        $bySource = $leads
            ->groupBy(fn (Lead $lead) => $lead->source->value)
            ->map(fn (Collection $group, string $value) => $this->channelRow($group->first()->source->label(), $group, $value))
            ->sortByDesc('total')
            ->values()
            ->all();

        $byCampaign = $leads
            ->filter(fn (Lead $lead) => filled($lead->utm_source))
            ->groupBy(fn (Lead $lead) => collect([$lead->utm_source, $lead->utm_medium, $lead->utm_campaign])->filter()->implode(' / '))
            ->map(fn (Collection $group, string $label) => $this->channelRow($label, $group))
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => $leads->count(),
            'converted' => $leads->whereNotNull('converted_at')->count(),
            'won_value' => $this->wonValue($leads),
            'by_source' => $bySource,
            'by_campaign' => $byCampaign,
        ];
    }

    /**
     * @param  Collection<int, Lead>  $leads
     * @param  string|null  $sourceValue  The raw LeadSource enum value, so the "By source" table can link each
     *                                    row to Lead Generation filtered to exactly those leads. Null for a
     *                                    "By campaign" row — there's no equivalent single-field filter for those.
     * @return array{label: string, total: int, converted: int, conversion_rate: int, won_value: int, avg_score: ?int, source_value: ?string}
     */
    private function channelRow(string $label, Collection $leads, ?string $sourceValue = null): array
    {
        $converted = $leads->whereNotNull('converted_at')->count();
        $scored = $leads->whereNotNull('ai_score');

        return [
            'label' => $label,
            'total' => $leads->count(),
            'converted' => $converted,
            'conversion_rate' => $leads->count() > 0 ? (int) round($converted / $leads->count() * 100) : 0,
            'won_value' => $this->wonValue($leads),
            'avg_score' => $scored->count() > 0 ? (int) round($scored->avg('ai_score')) : null,
            'source_value' => $sourceValue,
        ];
    }

    /**
     * @param  Collection<int, Lead>  $leads
     */
    private function wonValue(Collection $leads): int
    {
        return (int) $leads
            ->map(fn (Lead $lead) => $lead->convertedDeal)
            ->filter(fn ($deal) => $deal !== null && $deal->stage === DealStage::Won)
            ->sum('value');
    }

    /**
     * Score Calibration report — is the 0-100 AI score actually predictive of
     * outcome? Buckets closed Leads (Converted or Lost) by their ai_score
     * into the same Cold/Warm/Hot bands already shown on every lead's score
     * badge (see Lead::scoreBandFor()), with conversion rate and time-to-
     * close per bucket. ai_score itself is the "final snapshot" -- there's
     * no separate column, but LeadObserver guarantees a re-score on the
     * actual transition into a terminal status, so it's never stale by the
     * time a lead closes.
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
     * Loss Reason report — why Deals are actually being lost, cut four ways.
     * Scoped by stage_changed_at (when the deal reached Lost), not created_at
     * — "Lost deals in this window" means the loss happened here, not that
     * the deal originated here. stage_changed_at is safe to filter on because
     * Lost is terminal (Deal::moveToStage() forbids any further stage change,
     * so it can't have been bumped by an unrelated later edit).
     *
     * @return array{
     *     total: int,
     *     overall: list<array>,
     *     by_rep: list<array>,
     *     by_source: list<array>,
     *     by_score_band: list<array>,
     *     ai_suggestion_stats: array{accepted: int, overridden: int, no_suggestion: int, accepted_pct: int},
     * }
     */
    public function lossReasonBreakdown(Carbon $from, Carbon $to): array
    {
        $deals = Deal::query()
            ->where('stage', DealStage::Lost)
            ->whereBetween('stage_changed_at', [$from, $to])
            ->with(['owner', 'lead'])
            ->get();

        $total = $deals->count();
        $reasons = DealLostReason::cases();

        $overall = collect($reasons)
            ->map(fn (DealLostReason $reason) => $this->lossReasonRow($reason, $deals->filter(fn (Deal $d) => $d->lost_reason === $reason), $total))
            ->values()
            ->all();

        $byRep = $deals
            ->groupBy(fn (Deal $deal) => $deal->owner?->name ?? 'Unassigned')
            ->map(fn (Collection $group, string $rep) => [
                'label' => $rep,
                'total' => $group->count(),
                'by_reason' => collect($reasons)
                    ->map(fn (DealLostReason $reason) => $this->lossReasonRow($reason, $group->filter(fn (Deal $d) => $d->lost_reason === $reason), $group->count()))
                    ->values()->all(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $bySource = $deals
            ->groupBy(fn (Deal $deal) => $deal->lead?->source?->label() ?? 'Direct (no lead)')
            ->map(fn (Collection $group, string $source) => [
                'label' => $source,
                'total' => $group->count(),
                'by_reason' => collect($reasons)
                    ->map(fn (DealLostReason $reason) => $this->lossReasonRow($reason, $group->filter(fn (Deal $d) => $d->lost_reason === $reason), $group->count()))
                    ->values()->all(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $bandOrder = ['hot' => 0, 'warm' => 1, 'cold' => 2, 'no_score' => 3];
        $byScoreBand = $deals
            ->groupBy(fn (Deal $deal) => Lead::scoreBandFor($deal->lead?->ai_score) ?? 'no_score')
            ->map(fn (Collection $group, string $band) => [
                'label' => $band === 'no_score' ? 'No score data' : Lead::scoreBandLabel($band),
                'total' => $group->count(),
                'by_reason' => collect($reasons)
                    ->map(fn (DealLostReason $reason) => $this->lossReasonRow($reason, $group->filter(fn (Deal $d) => $d->lost_reason === $reason), $group->count()))
                    ->values()->all(),
            ])
            ->sortBy(fn (array $row, int|string $key) => $bandOrder[$key] ?? 4)
            ->values()
            ->all();

        $outcomes = $deals->countBy(fn (Deal $deal) => $deal->aiSuggestionOutcome());

        return [
            'total' => $total,
            'overall' => $overall,
            'by_rep' => $byRep,
            'by_source' => $bySource,
            'by_score_band' => $byScoreBand,
            'ai_suggestion_stats' => [
                'accepted' => $outcomes->get('accepted', 0),
                'overridden' => $outcomes->get('overridden', 0),
                'no_suggestion' => $outcomes->get('no_suggestion', 0),
                'accepted_pct' => $total > 0 ? (int) round($outcomes->get('accepted', 0) / $total * 100) : 0,
            ],
        ];
    }

    /**
     * @param  Collection<int, Deal>  $deals  Already filtered to this one reason within the current group.
     * @return array{reason: string, label: string, count: int, pct: int, value: int}
     */
    private function lossReasonRow(DealLostReason $reason, Collection $deals, int $groupTotal): array
    {
        $count = $deals->count();

        return [
            'reason' => $reason->value,
            'label' => $reason->label(),
            'count' => $count,
            'pct' => $groupTotal > 0 ? (int) round($count / $groupTotal * 100) : 0,
            'value' => (int) $deals->sum('value'),
        ];
    }
}
