<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Per-assignee task workload — the single source of truth for both the
 * Emptask team summary table (TaskController::index()) and the Team
 * Workload & Capacity dashboard, so "how many open/overdue tasks does this
 * person have" can never quietly disagree between the two pages.
 */
class TaskWorkloadMetrics
{
    /**
     * Roles that participate in workload/capacity tracking — same set
     * ReportMetrics::rankedEmployeePerformance() ranks (Admin/Manager are
     * evaluators, not participants, same distinction the Incentive module
     * and productivity ranking already make).
     *
     * @var list<string>
     */
    private const WORKLOAD_ROLES = [
        UserRole::Sales->value, UserRole::Support->value, UserRole::Accounts->value,
        UserRole::Intern->value, UserRole::Telecaller->value,
    ];

    /**
     * One row per assignee with at least one task, broken down by status,
     * plus how many are overdue (not a status, computed separately).
     *
     * @return Collection<int, array{user: User, total: int, todo: int, in_progress: int, review: int, done: int, overdue: int}>
     */
    public function perAssigneeSummary(): Collection
    {
        // toBase() bypasses Eloquent model hydration — without it, "status"
        // comes back cast to a TaskStatus enum instance (no __toString()),
        // and pluck('count', 'status') below would try to use that enum
        // object as a raw array key, which PHP rejects outright.
        $byStatus = Task::query()
            ->whereNotNull('assignee_id')
            ->selectRaw('assignee_id, status, count(*) as count')
            ->groupBy('assignee_id', 'status')
            ->toBase()
            ->get()
            ->groupBy('assignee_id');

        $overdueByAssignee = Task::query()
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->startOfDay())
            ->where('status', '!=', TaskStatus::Done->value)
            ->selectRaw('assignee_id, count(*) as count')
            ->groupBy('assignee_id')
            ->toBase()
            ->get()
            ->pluck('count', 'assignee_id');

        $users = User::whereIn('id', $byStatus->keys())->orderBy('name')->get()->keyBy('id');

        return $byStatus->map(function ($rows, $assigneeId) use ($overdueByAssignee, $users) {
            $counts = $rows->pluck('count', 'status');

            return [
                'user' => $users[$assigneeId] ?? null,
                'total' => (int) $rows->sum('count'),
                'todo' => (int) ($counts[TaskStatus::Todo->value] ?? 0),
                'in_progress' => (int) ($counts[TaskStatus::InProgress->value] ?? 0),
                'review' => (int) ($counts[TaskStatus::Review->value] ?? 0),
                'done' => (int) ($counts[TaskStatus::Done->value] ?? 0),
                'overdue' => (int) ($overdueByAssignee[$assigneeId] ?? 0),
            ];
        })
            ->filter(fn ($row) => $row['user'] !== null)
            ->sortBy(fn ($row) => $row['user']->name)
            ->values();
    }

    /**
     * Team Workload & Capacity dashboard: every active, workload-tracked-role
     * user (even one with zero tasks — an empty plate is itself a real
     * capacity signal a manager needs to see), grouped by role, with each
     * person's open (not-Done) task count compared against their role's own
     * average. overloaded = open_tasks > 1.5x the role average, OR 3+
     * overdue tasks (confirmed formula, 2026-08-09 — the ratio check alone
     * would never flag anyone in a single-person role group, since nobody
     * can exceed 1.5x their own value, so the overdue-count check is what
     * catches that case too).
     *
     * @return Collection<int, array{user: User, role: string, open_tasks: int, overdue_tasks: int, role_average_open_tasks: float, overloaded: bool}>
     */
    public function workloadByUser(): Collection
    {
        $summary = $this->perAssigneeSummary()->keyBy(fn (array $row) => $row['user']->id);

        $users = User::where('is_active', true)
            ->whereIn('role', self::WORKLOAD_ROLES)
            ->orderBy('name')
            ->get();

        $openTasksByUser = $users->mapWithKeys(function (User $user) use ($summary) {
            $row = $summary->get($user->id);
            $open = $row ? $row['total'] - $row['done'] : 0;

            return [$user->id => $open];
        });

        $roleAverages = $users->groupBy(fn (User $u) => $u->role->value)
            ->map(fn (Collection $group) => $group->avg(fn (User $u) => $openTasksByUser[$u->id]));

        return $users->map(function (User $user) use ($openTasksByUser, $roleAverages, $summary) {
            $open = $openTasksByUser[$user->id];
            $overdue = $summary->get($user->id)['overdue'] ?? 0;
            $roleAverage = $roleAverages[$user->role->value];

            return [
                'user' => $user,
                'role' => $user->role->value,
                'open_tasks' => $open,
                'overdue_tasks' => $overdue,
                'role_average_open_tasks' => round($roleAverage, 1),
                'overloaded' => ($roleAverage > 0 && $open > 1.5 * $roleAverage) || $overdue >= 3,
            ];
        })->values();
    }
}
