<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Partner;
use Illuminate\Support\Carbon;

/**
 * Single source of truth for partner commission math, shared by the live
 * Partner Panel dashboard (nothing stored, recalculated on every view) and
 * App\Console\Commands\FinalizePartnerCommissions (which snapshots the same
 * numbers into a locked PartnerCommissionStatement on the 1st of the next
 * month) — same "one calculator, two callers" shape as IncentiveCalculator.
 *
 * Deliberately a flat rate, not IncentiveCalculator's marginal/bracket
 * slabs — partners have individually negotiated rates, not a single
 * company-wide tiered structure. No team-bonus/SalesTarget equivalent
 * either: that backs a company-wide Sales pool, which has no partner
 * counterpart.
 */
class PartnerCommissionCalculator
{
    /** Sum of Deal.value for deals referred by this partner, marked Won within the given month. */
    public function monthlyReferralsForPartner(Partner $partner, Carbon $monthStart): int
    {
        return (int) Deal::query()
            ->where('partner_id', $partner->id)
            ->where('stage', DealStage::Won->value)
            ->whereNotNull('won_at')
            ->whereBetween('won_at', [$monthStart->copy()->startOfDay(), $monthStart->copy()->endOfMonth()])
            ->sum('value');
    }

    /**
     * Full commission breakdown for one partner for one month — the shape
     * both the live dashboard and FinalizePartnerCommissions build their
     * numbers from. Zero rate/referrals still returns a full shape (0
     * amount), never null — callers don't need a separate "no commission"
     * branch.
     */
    public function estimateForPartner(Partner $partner, Carbon $monthStart): array
    {
        $rate = (float) ($partner->commission_rate ?? 0);
        $referredValue = $this->monthlyReferralsForPartner($partner, $monthStart);
        $commissionAmount = (int) round($referredValue * $rate / 100);

        return [
            'referred_value' => $referredValue,
            'commission_rate' => $rate,
            'commission_amount' => $commissionAmount,
        ];
    }
}
