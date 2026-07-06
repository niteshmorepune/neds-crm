<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyReportMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DailyReportController extends Controller
{
    public function index(Request $request, DailyReportMetrics $metrics): View
    {
        $this->authorize('viewAny', DailyReport::class);

        $user = $request->user();
        $today = Carbon::today();

        $myTasks = Task::where('assignee_id', $user->id)
            ->where('status', '!=', TaskStatus::Done->value)
            ->with('project.customer')
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get();

        return view('daily-reports.index', [
            'metrics' => $metrics->for($user, $today),
            'todayReport' => DailyReport::where('user_id', $user->id)->whereDate('date', $today)->first(),
            'history' => DailyReport::where('user_id', $user->id)->latest('date')->paginate(15),
            'canViewTeam' => $user->can('viewTeam', DailyReport::class),
            'taskGroups' => $this->groupTasks($myTasks),
            'taskStatuses' => TaskStatus::cases(),
        ]);
    }

    public function store(Request $request, DailyReportMetrics $metrics): RedirectResponse
    {
        $this->authorize('viewAny', DailyReport::class);

        $data = $request->validate(['summary' => ['required', 'string', 'max:5000']]);

        $user = $request->user();
        $today = Carbon::today();

        // Match on the date part (whereDate) so re-submitting the same day
        // updates rather than duplicates, regardless of DB date storage.
        $report = DailyReport::where('user_id', $user->id)->whereDate('date', $today)->first()
            ?? new DailyReport(['user_id' => $user->id, 'date' => $today->toDateString()]);

        $report->fill($metrics->for($user, $today) + [
            'summary' => $data['summary'],
            'submitted_at' => now(),
        ])->save();

        return back()->with('status', 'Daily report submitted.');
    }

    public function team(Request $request): View
    {
        $this->authorize('viewTeam', DailyReport::class);

        $date = $request->date('date') ?? Carbon::today();

        return view('daily-reports.team', [
            'date' => $date,
            'users' => User::orderBy('name')->get(),
            'reports' => DailyReport::whereDate('date', $date)->get()->keyBy('user_id'),
        ]);
    }

    /**
     * Groups tasks by project (already sorted overdue/soonest-due first from
     * the query) and splits each project's tasks into "manual" (created_by is
     * set — someone assigned this directly) vs "routine" (created_by is null —
     * stamped out by app:dispatch-scheduled-tasks from a service template),
     * so recurring maintenance checks don't bury the few tasks a person
     * actually needs to act on. Project groups are then ordered by their
     * earliest due date, so the project needing attention soonest is first.
     *
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, array{project: Project|null, manual: Collection<int, Task>, routine: Collection<int, Task>}>
     */
    private function groupTasks(Collection $tasks): Collection
    {
        return $tasks
            ->groupBy(fn (Task $task) => $task->project_id ?? 0)
            ->map(function (Collection $group) {
                return [
                    'project' => $group->first()->project,
                    'manual' => $group->filter(fn (Task $task) => $task->created_by !== null)->values(),
                    'routine' => $group->filter(fn (Task $task) => $task->created_by === null)->values(),
                    'earliestDue' => $group->min(fn (Task $task) => $task->due_date?->timestamp) ?? PHP_INT_MAX,
                ];
            })
            ->sortBy('earliestDue')
            ->values();
    }
}
