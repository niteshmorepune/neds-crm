<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Manager panel doc's "Project Health Dashboard" (Tier 2 #03). Formula
 * confirmed with the owner via AskUserQuestion, 2026-08-09 — checked in
 * this order, first match wins:
 *
 *   Red:    end_date has passed (project still not Completed)
 *   Orange: end_date within 7 days AND completion% < 80%, OR the project
 *           has any overdue task (this second clause doesn't need an
 *           end_date at all — a project with no deadline can still have
 *           overdue tasks on it)
 *   Yellow: completion% < 50% AND more than half the project's own
 *           start_date-to-end_date timeline has already elapsed
 *   Green:  everything else
 *
 * Reuses Project::completionPercentage() (already built) rather than
 * re-deriving task-completion math. A project with no tasks yet
 * (completionPercentage() returns null) is treated as 0% for these
 * comparisons — deliberately: a project well into its timeline with
 * nothing logged yet is exactly the kind of thing this dashboard should
 * flag, not silently skip for lack of data.
 *
 * Only non-Completed projects are scored — a finished project has no
 * ongoing health to track.
 */
class ProjectHealthMetrics
{
    private const STATUS_ORDER = ['red' => 0, 'orange' => 1, 'yellow' => 2, 'green' => 3];

    /**
     * @return Collection<int, array{project: Project, status: string, completion: ?int, overdue_tasks: int, current_task: ?Task}>
     */
    public function healthByProject(): Collection
    {
        return Project::with(['customer', 'tasks.assignee'])
            ->where('status', '!=', ProjectStatus::Completed->value)
            ->get()
            ->map(fn (Project $project) => [
                'project' => $project,
                'status' => $this->statusFor($project),
                'completion' => $project->completionPercentage(),
                'overdue_tasks' => $this->overdueTaskCount($project),
                'current_task' => $this->currentTaskFor($project),
            ])
            ->sortBy(fn (array $row) => self::STATUS_ORDER[$row['status']])
            ->values();
    }

    /**
     * "What's being worked on right now" for the Dashboard's Ongoing
     * Projects widget — the soonest-due not-yet-Done task (undated tasks
     * sort last, since a task with no due date is less urgent than one
     * with a real deadline, not more), tie-broken by creation order.
     * Requires `tasks.assignee` already eager-loaded (see healthByProject())
     * — uses the in-memory collection, not a fresh query, so this stays
     * N+1-safe when called per project in a loop.
     */
    private function currentTaskFor(Project $project): ?Task
    {
        return $project->tasks
            ->where('status', '!=', TaskStatus::Done)
            ->sortBy([
                fn (Task $a, Task $b) => ($a->due_date?->timestamp ?? PHP_INT_MAX) <=> ($b->due_date?->timestamp ?? PHP_INT_MAX),
                fn (Task $a, Task $b) => $a->created_at <=> $b->created_at,
            ])
            ->first();
    }

    public function statusFor(Project $project): string
    {
        if ($project->status === ProjectStatus::Completed) {
            return 'green';
        }

        if ($project->end_date !== null && $project->end_date->isPast()) {
            return 'red';
        }

        $completion = $project->completionPercentage() ?? 0;
        $endingSoon = $project->end_date !== null && $project->end_date->between(now(), now()->addDays(7));

        if (($endingSoon && $completion < 80) || $this->overdueTaskCount($project) > 0) {
            return 'orange';
        }

        if ($project->start_date !== null && $project->end_date !== null) {
            $totalDays = $project->start_date->diffInDays($project->end_date);

            if ($totalDays > 0) {
                $elapsedDays = $project->start_date->diffInDays(now()->min($project->end_date));
                $elapsedPct = $elapsedDays / $totalDays * 100;

                if ($completion < 50 && $elapsedPct > 50) {
                    return 'yellow';
                }
            }
        }

        return 'green';
    }

    private function overdueTaskCount(Project $project): int
    {
        return $project->tasks
            ->where('status', '!=', TaskStatus::Done)
            ->filter(fn (Task $task) => $task->due_date !== null && $task->due_date->isPast())
            ->count();
    }
}
