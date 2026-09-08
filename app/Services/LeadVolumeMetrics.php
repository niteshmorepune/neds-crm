<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Day-by-day lead-capture counts for the Lead Sources report, split by
 * owner (Sales rep) and separately by telecaller — "how many did I get on
 * X day" is a different question from leadSourcePerformance()'s own
 * by-source/by-campaign monthly rollups, so it gets its own class per this
 * file's neighbor's own note (new reports shouldn't keep stacking onto
 * ReportMetrics). Columns are built from whoever actually owns/is
 * telecaller-assigned to a lead in the selected range (not a fixed active-
 * roster snapshot), so a rep who left mid-range still shows their real
 * historical numbers instead of being silently folded into "Unassigned."
 */
class LeadVolumeMetrics
{
    /**
     * @return array{
     *     days: list<string>,
     *     totals: array<string, int>,
     *     owner_columns: list<array{key: string, label: string}>,
     *     telecaller_columns: list<array{key: string, label: string}>,
     *     by_owner: array<string, array<string, int>>,
     *     by_telecaller: array<string, array<string, int>>,
     * }
     */
    public function dailyTrend(Carbon $from, Carbon $to): array
    {
        $tz = config('app.display_timezone', 'Asia/Kolkata');

        $leads = Lead::whereBetween('created_at', [$from, $to])->get(['created_at', 'owner_id', 'telecaller_id']);

        $days = [];
        foreach (CarbonPeriod::create($from->copy()->timezone($tz)->startOfDay(), '1 day', $to->copy()->timezone($tz)->startOfDay()) as $day) {
            $days[] = $day->toDateString();
        }

        $ownerColumns = $this->columnsFor($leads->pluck('owner_id'));
        $telecallerColumns = $this->columnsFor($leads->pluck('telecaller_id'));

        $totals = array_fill_keys($days, 0);
        $byOwner = array_fill_keys($days, array_fill_keys(array_column($ownerColumns, 'key'), 0));
        $byTelecaller = array_fill_keys($days, array_fill_keys(array_column($telecallerColumns, 'key'), 0));

        foreach ($leads as $lead) {
            $day = $lead->created_at->clone()->timezone($tz)->toDateString();

            if (! array_key_exists($day, $totals)) {
                continue; // a lead right at the range boundary bucketing into an adjacent IST day than the UTC-anchored $from/$to window covers — same boundary imprecision leadSourcePerformance()'s own month tiles already carry.
            }

            $totals[$day]++;
            $byOwner[$day][$lead->owner_id ?? 'unassigned']++;
            $byTelecaller[$day][$lead->telecaller_id ?? 'unassigned']++;
        }

        return [
            'days' => $days,
            'totals' => $totals,
            'owner_columns' => $ownerColumns,
            'telecaller_columns' => $telecallerColumns,
            'by_owner' => $byOwner,
            'by_telecaller' => $byTelecaller,
        ];
    }

    /**
     * Builds the column list for one dimension (owner or telecaller): a
     * real name per distinct non-null id actually present, alphabetical,
     * with an "Unassigned" column appended last only if at least one lead
     * in range genuinely has none.
     *
     * @param  Collection<int, ?int>  $ids
     * @return list<array{key: string, label: string}>
     */
    private function columnsFor($ids): array
    {
        $realIds = $ids->filter()->unique()->values();
        $hasUnassigned = $ids->contains(null);

        $names = User::whereIn('id', $realIds)->pluck('name', 'id');

        $columns = $realIds
            ->map(fn (int $id) => ['key' => (string) $id, 'label' => $names[$id] ?? "User #{$id}"])
            ->sortBy('label')
            ->values()
            ->all();

        if ($hasUnassigned) {
            $columns[] = ['key' => 'unassigned', 'label' => 'Unassigned'];
        }

        return $columns;
    }
}
