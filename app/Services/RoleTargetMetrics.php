<?php

namespace App\Services;

use App\Enums\TargetMetric;
use App\Enums\TargetPeriodType;
use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Payment;
use App\Models\RoleTarget;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Progress against each non-Sales role's one KRA target (App\Enums\
 * TargetMetric) — mirrors how SalesPipelineMetrics::targetProgress()/
 * salesLeaderboard() read SalesTarget, just generalized across 4 metrics
 * instead of one. Sales itself is untouched — it keeps reading SalesTarget
 * directly.
 */
class RoleTargetMetrics
{
    /**
     * The real count/amount this person actually achieved for $metric
     * between $from and $to. Each branch reuses this app's existing
     * "resolved" / "completed" / "recorded" conventions rather than
     * inventing a new one:
     * - tickets_resolved mirrors DraftMonthlyWinsNote's own query exactly
     *   (Resolved OR Closed, filtered on resolved_at).
     * - collections_recorded mirrors CollectionsMetrics' Payment-based
     *   collection figures, scoped to who recorded the payment.
     */
    public function actualValue(User $user, TargetMetric $metric, Carbon $from, Carbon $to): int
    {
        return match ($metric) {
            TargetMetric::TicketsResolved => Ticket::where('assignee_id', $user->id)
                ->whereIn('status', [TicketStatus::Resolved->value, TicketStatus::Closed->value])
                ->whereBetween('resolved_at', [$from, $to])
                ->count(),

            TargetMetric::CollectionsRecorded => (int) Payment::where('recorded_by', $user->id)
                ->whereBetween('paid_on', [$from->toDateString(), $to->toDateString()])
                ->sum('amount'),

            TargetMetric::TasksCompleted => Task::where('assignee_id', $user->id)
                ->where('status', TaskStatus::Done->value)
                ->whereBetween('completed_at', [$from, $to])
                ->count(),

            TargetMetric::CallsMade => CallLog::where('user_id', $user->id)
                ->whereBetween('called_at', [$from, $to])
                ->count(),
        };
    }

    /**
     * This calendar month's target/actual/pct for one person, using their
     * PRIMARY role's mapped metric (same primary-role-only convention as
     * DashboardController::index()'s panel selection — an additional role
     * never swaps which target someone is measured against). Null when
     * their role has no mapped KRA metric (Sales/Admin/Manager).
     *
     * @return array{metric: TargetMetric, target: ?int, actual: int, pct: ?int}|null
     */
    public function progressForUser(User $user): ?array
    {
        $metric = TargetMetric::forRole($user->role);
        if ($metric === null) {
            return null;
        }

        $periodStart = TargetPeriodType::Month->currentPeriodStart();
        $target = RoleTarget::query()
            ->forPeriod($user->id, $metric, TargetPeriodType::Month, $periodStart)
            ->value('target_value');
        $actual = $this->actualValue($user, $metric, $periodStart, now());

        return $this->row($metric, $target !== null ? (int) $target : null, $actual);
    }

    /**
     * One row per active user in $role plus a role-wide (company-within-
     * role) target/actual row, for the Team Targets admin page. $role must
     * have a mapped metric (TargetMetric::forRole()) — callers only ever
     * invoke this for the 4 roles Team Targets covers.
     *
     * @return array{metric: TargetMetric, roleWide: array, rows: Collection<int, array<string, mixed>>}
     */
    public function teamRows(UserRole $role): array
    {
        $metric = TargetMetric::forRole($role);
        abort_if($metric === null, 404);

        $periodStart = TargetPeriodType::Month->currentPeriodStart();
        $now = now();

        $rows = User::where('role', $role->value)->where('is_active', true)->orderBy('name')->get()
            ->map(function (User $user) use ($metric, $periodStart, $now) {
                $target = RoleTarget::query()
                    ->forPeriod($user->id, $metric, TargetPeriodType::Month, $periodStart)
                    ->value('target_value');
                $actual = $this->actualValue($user, $metric, $periodStart, $now);

                return array_merge(['user' => $user], $this->row($metric, $target !== null ? (int) $target : null, $actual));
            });

        $roleWideTarget = RoleTarget::query()
            ->forPeriod(null, $metric, TargetPeriodType::Month, $periodStart)
            ->value('target_value');

        return [
            'metric' => $metric,
            'roleWide' => $this->row($metric, $roleWideTarget !== null ? (int) $roleWideTarget : null, (int) $rows->sum('actual')),
            'rows' => $rows,
        ];
    }

    /**
     * @return array{metric: TargetMetric, target: ?int, actual: int, pct: ?int}
     */
    private function row(TargetMetric $metric, ?int $target, int $actual): array
    {
        return [
            'metric' => $metric,
            'target' => $target,
            'actual' => $actual,
            'pct' => $target !== null && $target > 0 ? (int) round($actual / $target * 100) : null,
        ];
    }
}
