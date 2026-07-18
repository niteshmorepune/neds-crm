<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\DealStageTransition;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Shared deal-pipeline calculations used by both the Sales Pipeline Kanban
 * board (App\Livewire\DealsBoard) and the Sales Dashboard
 * (App\Http\Controllers\SalesDashboardController) — kept in one place so the
 * two pages can never quietly disagree on the same-named figure.
 */
class SalesPipelineMetrics
{
    private const MIN_CONVERSION_SAMPLE = 5;

    private const STALE_STAGE_DAYS = 10;

    /**
     * Coaching-note specifics need fewer completed dwell periods to be
     * meaningful than a hard KPI does — MIN_CONVERSION_SAMPLE counts DEALS
     * entering a stage (a much more common event); this counts completed
     * dwell periods (a deal entering AND later leaving a stage), which
     * accumulate slower. Kept as its own constant rather than reusing
     * MIN_CONVERSION_SAMPLE since the two measure different things and may
     * want to diverge later.
     */
    private const MIN_DWELL_SAMPLE = 3;

    /**
     * KPI strip figures. Scoped by the same visibleTo() rule as the board
     * itself, so Sales reps see their own numbers and Admin/Manager see the
     * whole pipeline — unlike the company-wide, date-ranged win rate/avg
     * deal size/avg cycle in BusinessOverviewMetrics::pipelineFunnel()
     * (Reports > Pipeline & Funnel), which can't be reused here for that
     * reason. Whole-number rounding matches that report so the same-named
     * figures don't imply different precision.
     */
    public function kpis(User $user): array
    {
        $openByStage = Deal::query()
            ->visibleTo($user)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COALESCE(SUM(value), 0) as value')
            ->groupBy('stage')
            ->pluck('value', 'stage');

        $weightedForecast = 0;
        foreach ($openByStage as $stageValue => $sum) {
            $weightedForecast += (int) round($sum * DealStage::from($stageValue)->probability() / 100);
        }

        $closedCounts = Deal::query()
            ->visibleTo($user)
            ->whereIn('stage', [DealStage::Won->value, DealStage::Lost->value])
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');
        $wonCount = (int) ($closedCounts[DealStage::Won->value] ?? 0);
        $lostCount = (int) ($closedCounts[DealStage::Lost->value] ?? 0);

        $wonValues = Deal::query()->visibleTo($user)->where('stage', DealStage::Won->value)->pluck('value');

        $wonWithCycle = Deal::query()
            ->visibleTo($user)
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->get(['created_at', 'won_at']);

        $now = now();
        $fyStart = $this->financialYearStart($now);

        return [
            'open_pipeline_value' => (int) $openByStage->sum(),
            'weighted_forecast' => $weightedForecast,
            'won_this_month_value' => (int) Deal::query()
                ->visibleTo($user)
                ->where('stage', DealStage::Won->value)
                ->whereNotNull('won_at')
                ->where('won_at', '>=', $now->copy()->startOfMonth())
                ->sum('value'),
            'won_this_fy_value' => (int) Deal::query()
                ->visibleTo($user)
                ->where('stage', DealStage::Won->value)
                ->whereNotNull('won_at')
                ->where('won_at', '>=', $fyStart)
                ->sum('value'),
            'win_rate' => ($wonCount + $lostCount) > 0 ? (int) round($wonCount / ($wonCount + $lostCount) * 100) : null,
            'avg_deal_size' => $wonValues->isNotEmpty() ? (int) round($wonValues->avg()) : null,
            'avg_sales_cycle_days' => $wonWithCycle->isNotEmpty()
                ? (int) round($wonWithCycle->avg(fn (Deal $deal) => $deal->created_at->diffInDays($deal->won_at)))
                : null,
        ];
    }

    /**
     * Stage-to-stage advance rate for each adjacent pair in the pipeline
     * sequence, e.g. what % of deals that ever reached "Contacted" went on
     * to reach "Proposal". Built from deal_stage_transitions, which only
     * starts accumulating the day this feature shipped — so each pair
     * reports null (rendered as "Not enough data yet") until at least
     * self::MIN_CONVERSION_SAMPLE deals have entered the "from" stage.
     * Scoped by the same visibleTo() rule as the rest of the board via
     * whereHas('deal', ...).
     */
    public function stageConversion(User $user): array
    {
        $sequence = [DealStage::New, DealStage::Contacted, DealStage::Proposal, DealStage::Negotiation, DealStage::Won];

        $transitions = DealStageTransition::query()
            ->whereHas('deal', fn ($q) => $q->visibleTo($user))
            ->get(['deal_id', 'from_stage', 'to_stage']);

        $pairs = [];
        for ($i = 0; $i < count($sequence) - 1; $i++) {
            $from = $sequence[$i];
            $to = $sequence[$i + 1];

            $entered = $transitions->where('to_stage', $from)->pluck('deal_id')->unique();
            $advanced = $transitions->where('from_stage', $from)->where('to_stage', $to)->pluck('deal_id')->unique();

            $pairs[] = [
                'from' => $from,
                'to' => $to,
                'rate' => $entered->count() >= self::MIN_CONVERSION_SAMPLE
                    ? (int) round($advanced->count() / $entered->count() * 100)
                    : null,
            ];
        }

        return $pairs;
    }

    /**
     * Per-rep average days spent in each non-terminal stage before moving
     * on, alongside the team-wide average for the same stage — gives the
     * Team Performance Summary AI narration a concrete specific ("stalls
     * longest in Negotiation, 18 days vs the team's 9") instead of vague
     * "might need support" language. A dwell period is the time between a
     * deal entering a stage and the NEXT transition on that same deal —
     * built from deal_stage_transitions, which only accumulates forward
     * from when that feature shipped (2026-07-16), same caveat as
     * stageConversion(). Deliberately unscoped (Admin/Manager-only caller,
     * same as repLeaderboard()) — a coaching summary needs every rep's
     * numbers, not just the viewer's own.
     *
     * @return array<int, array<string, array{rep_avg_days: float, rep_sample: int, team_avg_days: float, team_sample: int}>>
     *                                                                                                                        keyed by rep user id, then DealStage value — a stage is only
     *                                                                                                                        present for a rep once both that rep's AND the team's sample
     *                                                                                                                        reach MIN_DWELL_SAMPLE, so a young dataset simply omits
     *                                                                                                                        everyone rather than showing a shaky number.
     */
    public function repStageDwellTimes(): array
    {
        $nonTerminal = collect(DealStage::cases())->reject(fn (DealStage $s) => $s->isTerminal())->map(fn (DealStage $s) => $s->value);

        $transitions = DealStageTransition::query()
            ->with('deal:id,owner_id')
            ->orderBy('deal_id')
            ->orderBy('created_at')
            ->get(['deal_id', 'to_stage', 'created_at']);

        // teamDwells[stage] = list<float days>; repDwells[owner_id][stage] = list<float days>
        $teamDwells = [];
        $repDwells = [];

        foreach ($transitions->groupBy('deal_id') as $dealTransitions) {
            $ownerId = $dealTransitions->first()->deal?->owner_id;
            $ordered = $dealTransitions->values();

            for ($i = 0; $i < $ordered->count() - 1; $i++) {
                $current = $ordered[$i];
                $next = $ordered[$i + 1];

                if (! $nonTerminal->contains($current->to_stage->value)) {
                    continue;
                }

                $days = $current->created_at->diffInHours($next->created_at) / 24;

                $teamDwells[$current->to_stage->value][] = $days;

                if ($ownerId !== null) {
                    $repDwells[$ownerId][$current->to_stage->value][] = $days;
                }
            }
        }

        $teamAverages = [];
        foreach ($teamDwells as $stage => $days) {
            if (count($days) >= self::MIN_DWELL_SAMPLE) {
                $teamAverages[$stage] = ['avg' => array_sum($days) / count($days), 'sample' => count($days)];
            }
        }

        $result = [];
        foreach ($repDwells as $ownerId => $stages) {
            foreach ($stages as $stage => $days) {
                if (count($days) < self::MIN_DWELL_SAMPLE || ! isset($teamAverages[$stage])) {
                    continue;
                }

                $result[$ownerId][$stage] = [
                    'rep_avg_days' => round(array_sum($days) / count($days), 1),
                    'rep_sample' => count($days),
                    'team_avg_days' => round($teamAverages[$stage]['avg'], 1),
                    'team_sample' => $teamAverages[$stage]['sample'],
                ];
            }
        }

        return $result;
    }

    /**
     * Won deal value per month for the last $months (oldest first) — feeds
     * the Sales Dashboard trend chart.
     *
     * @return list<array{label: string, value: int}>
     */
    public function wonValueTrend(User $user, int $months = 12): array
    {
        $now = now();
        $start = $now->copy()->startOfMonth()->subMonths($months - 1);

        $rows = Deal::query()
            ->visibleTo($user)
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->where('won_at', '>=', $start)
            ->get(['value', 'won_at'])
            ->groupBy(fn (Deal $deal) => $deal->won_at->format('Y-m'));

        $trend = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $trend[] = [
                'label' => $month->format('M Y'),
                'value' => (int) ($rows->get($key)?->sum('value') ?? 0),
            ];
        }

        return $trend;
    }

    /**
     * Pipeline value / won-this-month value / win rate broken down per
     * service line, so the owner can see which services actually convert.
     */
    public function serviceBreakdown(User $user): array
    {
        $deals = Deal::query()
            ->visibleTo($user)
            ->with('service:id,name')
            ->get(['service_id', 'stage', 'value', 'won_at']);

        $now = now();

        return $deals->groupBy(fn (Deal $deal) => $deal->service_id)
            ->map(function (Collection $group) use ($now) {
                $open = $group->whereNotIn('stage', [DealStage::Won, DealStage::Lost]);
                $won = $group->where('stage', DealStage::Won);
                $lost = $group->where('stage', DealStage::Lost);
                $wonThisMonth = $won->filter(fn (Deal $d) => $d->won_at && $d->won_at->greaterThanOrEqualTo($now->copy()->startOfMonth()));
                $closedCount = $won->count() + $lost->count();

                return [
                    'service' => $group->first()->service?->name ?? 'No service',
                    'open_pipeline_value' => (int) $open->sum('value'),
                    'won_this_month_value' => (int) $wonThisMonth->sum('value'),
                    'win_rate' => $closedCount > 0 ? (int) round($won->count() / $closedCount * 100) : null,
                ];
            })
            ->sortByDesc('open_pipeline_value')
            ->values()
            ->all();
    }

    /**
     * Deals that need a human to look at them: stale in their current
     * stage, overdue for follow-up, unowned, or missing a value entirely
     * (the last one surfaces pre-existing ₹0 deals from before `value`
     * became a required field).
     */
    public function needsAttention(User $user): array
    {
        $openDeals = fn () => Deal::query()
            ->visibleTo($user)
            ->whereNotIn('stage', [DealStage::Won->value, DealStage::Lost->value]);

        return [
            'stale' => $openDeals()
                ->where('stage_changed_at', '<=', now()->subDays(self::STALE_STAGE_DAYS))
                ->with(['customer:id,company_name'])
                ->orderBy('stage_changed_at')
                ->get(['id', 'title', 'customer_id', 'stage', 'stage_changed_at']),
            'overdue_followups' => $openDeals()
                ->whereNotNull('next_follow_up_at')
                ->where('next_follow_up_at', '<=', now())
                ->with(['customer:id,company_name'])
                ->orderBy('next_follow_up_at')
                ->get(['id', 'title', 'customer_id', 'next_follow_up_at']),
            'unowned' => $openDeals()
                ->whereNull('owner_id')
                ->with(['customer:id,company_name'])
                ->get(['id', 'title', 'customer_id']),
            'zero_value' => $openDeals()
                ->where('value', 0)
                ->with(['customer:id,company_name'])
                ->get(['id', 'title', 'customer_id']),
        ];
    }

    /**
     * Per-rep pipeline value, this-month won value, win rate, avg deal
     * size and target progress. Admin/Manager only — deliberately
     * unscoped by visibleTo() since the caller already knows to restrict
     * this view to roles that can see everyone.
     */
    public function repLeaderboard(): array
    {
        $reps = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->orderBy('name')->get();

        return $reps->map(function (User $rep) {
            $deals = Deal::query()->where('owner_id', $rep->id)->get(['stage', 'value', 'won_at']);
            $open = $deals->whereNotIn('stage', [DealStage::Won, DealStage::Lost]);
            $won = $deals->where('stage', DealStage::Won);
            $lost = $deals->where('stage', DealStage::Lost);
            $wonThisMonth = $won->filter(fn (Deal $d) => $d->won_at && $d->won_at->greaterThanOrEqualTo(now()->startOfMonth()));
            $closedCount = $won->count() + $lost->count();

            $target = SalesTarget::query()
                ->forPeriod($rep->id, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())
                ->value('target_value');

            $wonThisMonthValue = (int) $wonThisMonth->sum('value');

            return [
                'user' => $rep,
                'pipeline_value' => (int) $open->sum('value'),
                'won_this_month_value' => $wonThisMonthValue,
                'target_value' => $target !== null ? (int) $target : null,
                'target_pct' => $target !== null && $target > 0 ? (int) round($wonThisMonthValue / $target * 100) : null,
                'win_rate' => $closedCount > 0 ? (int) round($won->count() / $closedCount * 100) : null,
                'avg_deal_size' => $won->isNotEmpty() ? (int) round($won->avg('value')) : null,
            ];
        })->all();
    }

    /**
     * Target-vs-actual for the dashboard's progress bars. Admin/Manager see
     * the company-wide target (user_id null) against the whole pipeline's
     * KPIs; a Sales rep sees their own target against their own
     * visibleTo()-scoped KPIs — matching how the KPI strip itself already
     * splits "everyone" vs "just me" by role.
     */
    public function targetProgress(User $user, array $kpis): array
    {
        $targetUserId = $user->hasRole(UserRole::Admin, UserRole::Manager) ? null : $user->id;

        $monthly = SalesTarget::query()
            ->forPeriod($targetUserId, TargetPeriodType::Month, TargetPeriodType::Month->currentPeriodStart())
            ->value('target_value');

        $fy = SalesTarget::query()
            ->forPeriod($targetUserId, TargetPeriodType::FinancialYear, TargetPeriodType::FinancialYear->currentPeriodStart())
            ->value('target_value');

        return [
            'monthly' => $this->progressRow($monthly, $kpis['won_this_month_value']),
            'fy' => $this->progressRow($fy, $kpis['won_this_fy_value']),
        ];
    }

    private function progressRow(?int $target, int $actual): ?array
    {
        if ($target === null) {
            return null;
        }

        return [
            'target' => $target,
            'actual' => $actual,
            'pct' => $target > 0 ? (int) round($actual / $target * 100) : null,
        ];
    }

    private function financialYearStart(Carbon $now): Carbon
    {
        $fyStartYear = $now->month >= 4 ? $now->year : $now->year - 1;

        return Carbon::create($fyStartYear, 4, 1)->startOfDay();
    }
}
