<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\IncentiveSetting;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for the Sales Incentive slab math, shared by the
 * live /incentives dashboard (nothing stored, recalculated on every view)
 * and App\Console\Commands\FinalizeIncentives (which snapshots the same
 * numbers into a locked IncentiveStatement on the 1st of the next month) —
 * same "one calculator, two callers" shape as SalesPipelineMetrics.
 *
 * Slab rates are marginal/bracket-style (like income tax), not a cliff —
 * confirmed with the owner specifically to avoid reps holding back a deal
 * near a bracket boundary. Bounds and rates per the owner's incentive
 * structure: 6% up to ₹50k, 10% up to ₹1L, 12.5% up to ₹1.5L, 15% up to
 * ₹2.5L, 20% above.
 */
class IncentiveCalculator
{
    /**
     * @var list<array{lower: int, upper: int|null, rate: float}>
     *                                                            Bounds in paise; upper = null means "and above".
     */
    private const BRACKETS = [
        ['lower' => 0, 'upper' => 50_000 * 100, 'rate' => 6.0],
        ['lower' => 50_000 * 100, 'upper' => 100_000 * 100, 'rate' => 10.0],
        ['lower' => 100_000 * 100, 'upper' => 150_000 * 100, 'rate' => 12.5],
        ['lower' => 150_000 * 100, 'upper' => 250_000 * 100, 'rate' => 15.0],
        ['lower' => 250_000 * 100, 'upper' => null, 'rate' => 20.0],
    ];

    /** Marginal-bracket incentive on a before-tax sales figure, in paise. */
    public function slabIncentive(int $salesValuePaise): int
    {
        $incentive = 0.0;

        foreach (self::BRACKETS as $bracket) {
            $bandTop = $bracket['upper'] ?? $salesValuePaise;
            $portion = min($salesValuePaise, $bandTop) - $bracket['lower'];

            if ($portion <= 0) {
                continue;
            }

            $incentive += $portion * $bracket['rate'] / 100;
        }

        return (int) round($incentive);
    }

    /** The bracket the rep's current sales value falls into (marginal rate now applying). */
    public function currentSlab(int $salesValuePaise): array
    {
        foreach (array_reverse(self::BRACKETS) as $bracket) {
            if ($salesValuePaise >= $bracket['lower']) {
                return $bracket;
            }
        }

        return self::BRACKETS[0];
    }

    /** The next bracket up, or null if already in the top bracket. */
    public function nextSlab(int $salesValuePaise): ?array
    {
        foreach (self::BRACKETS as $bracket) {
            if ($bracket['lower'] > $salesValuePaise) {
                return $bracket;
            }
        }

        return null;
    }

    /** Sum of Deal.value for deals this user owns, marked Won within the given month. */
    public function monthlySalesForUser(User $user, Carbon $monthStart): int
    {
        return (int) Deal::query()
            ->where('owner_id', $user->id)
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->whereBetween('won_at', [$monthStart->copy()->startOfDay(), $monthStart->copy()->endOfMonth()])
            ->sum('value');
    }

    /** Whole-company Won value for the given month, for comparison against the company-wide target. */
    public function companySalesForMonth(Carbon $monthStart): int
    {
        return (int) Deal::query()
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->whereBetween('won_at', [$monthStart->copy()->startOfDay(), $monthStart->copy()->endOfMonth()])
            ->sum('value');
    }

    /** Whether the company-wide monthly SalesTarget was met or exceeded. False if no target is set. */
    public function companyTargetMet(Carbon $monthStart): bool
    {
        $target = SalesTarget::query()
            ->forPeriod(null, TargetPeriodType::Month, $monthStart)
            ->value('target_value');

        if ($target === null) {
            return false;
        }

        return $this->companySalesForMonth($monthStart) >= (int) $target;
    }

    /** This rep's even split of the team-bonus pool, or 0 if the company target wasn't met or no pool is set. */
    public function teamBonusShare(Carbon $monthStart): int
    {
        if (! $this->companyTargetMet($monthStart)) {
            return 0;
        }

        $pool = IncentiveSetting::current()->team_bonus_pool;

        if ($pool <= 0) {
            return 0;
        }

        $eligibleCount = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->count();

        if ($eligibleCount === 0) {
            return 0;
        }

        return intdiv($pool, $eligibleCount);
    }

    /**
     * Full incentive breakdown for one rep for one month — the shape both the
     * live dashboard and FinalizeIncentives build their numbers from.
     */
    public function estimateForUser(User $user, Carbon $monthStart): array
    {
        $salesValue = $this->monthlySalesForUser($user, $monthStart);
        $individualIncentive = $this->slabIncentive($salesValue);
        $teamBonusEligible = $this->companyTargetMet($monthStart);
        $teamBonusShare = $this->teamBonusShare($monthStart);

        return [
            'sales_value' => $salesValue,
            'individual_incentive' => $individualIncentive,
            'team_bonus_eligible' => $teamBonusEligible,
            'team_bonus_share' => $teamBonusShare,
            'total_incentive' => $individualIncentive + $teamBonusShare,
            'current_slab' => $this->currentSlab($salesValue),
            'next_slab' => $this->nextSlab($salesValue),
        ];
    }
}
