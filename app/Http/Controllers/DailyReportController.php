<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\DailyReportMetrics;
use Carbon\CarbonPeriod;
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
        $users = User::orderBy('name')->get();

        return view('daily-reports.team', [
            'date' => $date,
            'users' => $users,
            'reports' => DailyReport::whereDate('date', $date)->get()->keyBy('user_id'),
            'weeklyRates' => $this->weeklySubmissionRates($users, $date),
        ]);
    }

    /**
     * For each user, how many of the last 7 calendar days ending on $date —
     * excluding Sundays, since the office is Mon-Sat — have a submitted
     * daily report. Lets admin spot a chronic non-submitter without
     * flipping through the date picker one day at a time.
     *
     * @return Collection<int, array{submitted: int, expected: int}>
     */
    private function weeklySubmissionRates(Collection $users, Carbon $date): Collection
    {
        $businessDays = collect(CarbonPeriod::create($date->copy()->subDays(6), $date))
            ->reject(fn (Carbon $d) => $d->isSunday())
            ->map(fn (Carbon $d) => $d->toDateString());

        // Range query + PHP-side date comparison, not an exact-match whereIn
        // on the raw column: SQLite serializes a `date`-cast column with a
        // trailing time component, so exact string matching against
        // toDateString() values silently matches nothing there (MySQL
        // wouldn't have this issue, but the test suite runs on SQLite).
        // $report->date is a Carbon instance either way (the model's own
        // cast), so normalizing via toDateString() in PHP works regardless
        // of how the underlying driver stored it.
        $submittedCounts = DailyReport::query()
            ->whereBetween('date', [$date->copy()->subDays(6)->startOfDay(), $date->copy()->endOfDay()])
            ->get(['user_id', 'date'])
            ->filter(fn (DailyReport $report) => $businessDays->contains($report->date->toDateString()))
            ->groupBy('user_id')
            ->map->count();

        return $users->mapWithKeys(fn (User $user) => [
            $user->id => [
                'submitted' => (int) ($submittedCounts[$user->id] ?? 0),
                'expected' => $businessDays->count(),
            ],
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
