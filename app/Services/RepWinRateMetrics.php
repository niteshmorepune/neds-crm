<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\RepWinRateSnapshot;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rep Win Rate tracking -- "Lead to Won" Phase 3, Task 2. Measurement
 * only: nothing in this class is called from ScoreLead, LeadAssignmentRule,
 * or any other scoring/routing decision. This is "make the number
 * visible," not "make the number matter yet" -- a future routing-tuning
 * decision would need this data, but that decision isn't being made here.
 */
class RepWinRateMetrics
{
    /**
     * Live calculation, called only by App\Console\Commands\
     * SnapshotRepWinRates -- the report page itself never calls this; it
     * reads only what was already snapshotted (see forPeriod()).
     *
     * Base population is every active Sales user (same withAnyRole(Sales)
     * pool ReassignmentMetrics/LeadObserver already use), unioned with any
     * user who actually owns a closed deal in the period, so an Admin/
     * Manager who occasionally owns a deal directly still gets counted
     * rather than silently dropped.
     *
     * Closed deals are scoped by stage_changed_at, matching
     * LossReasonMetrics's own choice for Lost -- both Won and Lost are
     * terminal (Deal::moveToStage() forbids any further stage change), so
     * it's safe to filter either on when it actually closed.
     *
     * @return list<array{user_id: int, user: string, overall: array, by_source: list<array>, by_score_band: list<array>}>
     */
    public function calculate(Carbon $from, Carbon $to): array
    {
        $deals = Deal::query()
            ->whereIn('stage', [DealStage::Won, DealStage::Lost])
            ->whereBetween('stage_changed_at', [$from, $to])
            ->whereNotNull('owner_id')
            ->with('lead')
            ->get();

        $repIds = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->pluck('id')
            ->merge($deals->pluck('owner_id'))
            ->filter()
            ->unique();

        $users = User::whereIn('id', $repIds)->get()->keyBy('id');

        return $users->map(function (User $user) use ($deals) {
            $repDeals = $deals->where('owner_id', $user->id);

            return [
                'user_id' => $user->id,
                'user' => $user->name,
                'overall' => $this->winRateRow($repDeals),
                'by_source' => $this->bySource($repDeals),
                'by_score_band' => $this->byScoreBand($repDeals),
            ];
        })->values()->all();
    }

    /**
     * @param  Collection<int, Deal>  $deals  Already scoped to one rep.
     * @return list<array>
     */
    private function bySource(Collection $deals): array
    {
        return $deals
            ->groupBy(fn (Deal $d) => $d->lead?->source?->value ?? 'direct')
            ->map(function (Collection $group, string $sourceValue) {
                $row = $this->winRateRow($group);
                $row['dimension_value'] = $sourceValue;
                $row['label'] = $sourceValue === 'direct' ? 'Direct (no lead)' : LeadSource::from($sourceValue)->label();

                return $row;
            })
            ->sortByDesc(fn (array $r) => $r['won_count'] + $r['lost_count'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Deal>  $deals  Already scoped to one rep.
     * @return list<array>
     */
    private function byScoreBand(Collection $deals): array
    {
        return $deals
            ->groupBy(fn (Deal $d) => Lead::scoreBandFor($d->lead?->ai_score) ?? 'no_score')
            ->map(function (Collection $group, string $band) {
                $row = $this->winRateRow($group);
                $row['dimension_value'] = $band;
                $row['label'] = $band === 'no_score' ? 'No score data' : Lead::scoreBandLabel($band);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Deal>  $deals
     * @return array{won_count: int, lost_count: int, win_rate: ?int}
     */
    private function winRateRow(Collection $deals): array
    {
        $won = $deals->where('stage', DealStage::Won)->count();
        $lost = $deals->where('stage', DealStage::Lost)->count();
        $total = $won + $lost;

        return [
            'won_count' => $won,
            'lost_count' => $lost,
            'win_rate' => $total > 0 ? (int) round($won / $total * 100) : null,
        ];
    }

    /**
     * Reads the already-recorded snapshots for one exact month -- pure
     * read, never recomputes live. Returns [] if no snapshot has been
     * taken for this period yet (e.g. the current, still-open month).
     *
     * @return list<array{user: string, overall: array, by_source: list<array>, by_score_band: list<array>}>
     */
    public function forPeriod(Carbon $monthStart): array
    {
        $snapshots = RepWinRateSnapshot::query()
            ->whereDate('period_start', $monthStart)
            ->with('user')
            ->get()
            ->filter(fn (RepWinRateSnapshot $s) => $s->user !== null);

        return $snapshots
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                $overall = $group->firstWhere('dimension', 'overall');

                return [
                    'user' => $group->first()->user->name,
                    'overall' => [
                        'won_count' => $overall?->won_count ?? 0,
                        'lost_count' => $overall?->lost_count ?? 0,
                        'win_rate' => $overall?->win_rate,
                    ],
                    'by_source' => $this->snapshotDimensionRows($group, 'source'),
                    'by_score_band' => $this->snapshotDimensionRows($group, 'score_band'),
                ];
            })
            ->sortByDesc(fn (array $row) => $row['overall']['won_count'] + $row['overall']['lost_count'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, RepWinRateSnapshot>  $snapshotsForUser
     * @return list<array>
     */
    private function snapshotDimensionRows(Collection $snapshotsForUser, string $dimension): array
    {
        return $snapshotsForUser
            ->where('dimension', $dimension)
            ->map(function (RepWinRateSnapshot $s) use ($dimension) {
                $label = match (true) {
                    $dimension === 'source' && $s->dimension_value === 'direct' => 'Direct (no lead)',
                    $dimension === 'source' => LeadSource::tryFrom($s->dimension_value)?->label() ?? $s->dimension_value,
                    $dimension === 'score_band' && $s->dimension_value === 'no_score' => 'No score data',
                    default => Lead::scoreBandLabel($s->dimension_value),
                };

                return [
                    'dimension_value' => $s->dimension_value,
                    'label' => $label,
                    'won_count' => $s->won_count,
                    'lost_count' => $s->lost_count,
                    'win_rate' => $s->win_rate,
                ];
            })
            ->sortByDesc(fn (array $r) => $r['won_count'] + $r['lost_count'])
            ->values()
            ->all();
    }
}
