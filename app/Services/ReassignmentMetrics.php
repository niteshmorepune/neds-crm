<?php

namespace App\Services;

use App\Enums\LeadReassignmentReason;
use App\Enums\UserRole;
use App\Models\LeadReassignment;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reassignment Analytics — extracted from ReportMetrics (2026-09-01) as its
 * own service, same reasoning as ScoreCalibrationMetrics/LossReasonMetrics.
 * Pure move, no behavior change.
 */
class ReassignmentMetrics
{
    /**
     * A management signal that's already captured (App\Actions\ReassignLead
     * logs every handoff with a reason) and never reported on. Deliberately
     * simple: one row per rep, not the multi-cut treatment the Loss Reason
     * report gets.
     *
     * Base population is every active Sales user (leads are a Sales-owned
     * resource; LeadObserver::resolveLeastLoadedSales() uses the same
     * withAnyRole(Sales) pool) so a rep with zero reassignments this period
     * still gets a row showing a clean zero, not a missing one -- unioned
     * with any user who actually appears in this period's reassignment
     * records, covering the rarer case of an Admin/Manager party (see
     * LeadReassignRequest's own docblock: Admin/Manager may hand a lead to
     * any active Sales/Manager/Admin user, not Sales-to-Sales only).
     *
     * @return array{total: int, rows: list<array>}
     */
    public function reassignmentAnalytics(Carbon $from, Carbon $to): array
    {
        $reassignments = LeadReassignment::query()
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $repIds = User::query()->where('is_active', true)->withAnyRole(UserRole::Sales)->pluck('id')
            ->merge($reassignments->pluck('from_user_id'))
            ->merge($reassignments->pluck('to_user_id'))
            ->filter()
            ->unique();

        $users = User::whereIn('id', $repIds)->get()->keyBy('id');

        $rows = $users
            ->map(function (User $user) use ($reassignments) {
                $away = $reassignments->where('from_user_id', $user->id);
                $to = $reassignments->where('to_user_id', $user->id);

                return [
                    'user' => $user->name,
                    'reassigned_away_count' => $away->count(),
                    'reassigned_away_reasons' => $away
                        ->countBy(fn (LeadReassignment $r) => $r->reason->value)
                        ->map(fn (int $count, string $reasonValue) => [
                            'reason' => $reasonValue,
                            'label' => LeadReassignmentReason::from($reasonValue)->label(),
                            'count' => $count,
                        ])
                        ->values()
                        ->sortByDesc('count')
                        ->values()
                        ->all(),
                    'reassigned_to_count' => $to->count(),
                ];
            })
            ->sortByDesc('reassigned_away_count')
            ->values()
            ->all();

        return [
            'total' => $reassignments->count(),
            'rows' => $rows,
        ];
    }
}
