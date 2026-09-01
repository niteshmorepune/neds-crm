<?php

namespace App\Services;

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Loss Reason report — extracted from ReportMetrics (2026-09-01) as its own
 * service, same reasoning as ScoreCalibrationMetrics: three Phase 2 report
 * PRs landing the same day all collided on ReportMetrics.php. Pure move, no
 * behavior change.
 */
class LossReasonMetrics
{
    /**
     * Why Deals are actually being lost, cut four ways. Scoped by
     * stage_changed_at (when the deal reached Lost), not created_at —
     * "Lost deals in this window" means the loss happened here, not that
     * the deal originated here. stage_changed_at is safe to filter on
     * because Lost is terminal (Deal::moveToStage() forbids any further
     * stage change, so it can't have been bumped by an unrelated later
     * edit).
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
