<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\EmployeeActivityTimeline;
use App\Services\ReportMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Employee 360° View — one consolidated employee page (performance,
 * workload, tickets, attendance, manager notes), per the Manager panel
 * doc's ask. Deliberately its own controller/route/menu item rather than
 * extending UserController: UserController's every action stays
 * Admin-only account management (create/edit/deactivate/reset password —
 * routes/web.php's users.* group, gated by menu.access:users), while this
 * is a narrower, read-mostly view (+ notes) newly opened to Manager too —
 * resolves the "Manager access to employee notes" question deferred when
 * Employee Notes was first added to users/edit.blade.php (Admin-only
 * there, unchanged by this).
 *
 * No UserPolicy exists in this app, and no inline role check here either —
 * access is purely menu.access:employee-360 (routes/web.php), same
 * convention as ClientRadarController/FestivalController/ServiceController
 * for admin/manager-only pages with no Policy class. Confirmed via
 * MenuResolver's own doc-comment before relying on it: accessibleKeys()/
 * canAccess() (real route access) is role-based only — per-user Menu
 * Controller overrides only ever affect sidebar VISIBILITY, never actual
 * access (MenuAccessTest covers this: a per-user "granted" override reveals
 * a hidden item but the route still 403s). So a redundant inline hasRole()
 * check here wouldn't change who gets in — it would just be a second,
 * independently-maintained copy of the same role list already in
 * MenuItemsSeeder, free to quietly drift from it. Deleted rather than kept
 * "for safety."
 */
class EmployeeProfileController extends Controller
{
    public function index(): View
    {
        $employees = User::where('is_active', true)->orderBy('name')->get();

        return view('employees.index', compact('employees'));
    }

    public function show(Request $request, User $user, ReportMetrics $metrics, EmployeeActivityTimeline $timeline): View
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

        // Activity Timeline has its own, independently-picked date range
        // (default: today) — the performance summary above stays fixed to
        // "this month" regardless, so switching timeline dates never makes
        // that section look like it changed too.
        //
        // Parsed against app.display_timezone (Asia/Kolkata), not
        // Carbon::parse()'s default of app.timezone (UTC) — the same class
        // of off-by-5:30 bug already caught and fixed once in this app
        // (Create Meeting's timezone bug, 2026-07-29); a plain "2026-08-17"
        // date input must resolve to IST midnight, not UTC midnight.
        $displayTz = config('app.display_timezone', 'Asia/Kolkata');
        $timelineFrom = $request->filled('from')
            ? Carbon::createFromFormat('Y-m-d', $request->string('from')->value(), $displayTz)->startOfDay()->utc()
            : now($displayTz)->startOfDay()->utc();
        $timelineTo = $request->filled('to')
            ? Carbon::createFromFormat('Y-m-d', $request->string('to')->value(), $displayTz)->endOfDay()->utc()
            : now($displayTz)->endOfDay()->utc();

        // rankedEmployeePerformance() (not the plain employeePerformance())
        // so this page surfaces the same score/rank/weakest_metric coaching
        // signal already built for the Employee Performance report, instead
        // of re-deriving a narrower one-user version of it.
        $performance = $metrics->rankedEmployeePerformance($from, $to)->firstWhere('user_id', $user->id);

        $tasks = Task::where('assignee_id', $user->id);
        $workload = [
            'total' => (int) $tasks->clone()->count(),
            'pending' => (int) $tasks->clone()->where('status', '!=', TaskStatus::Done->value)->count(),
            'overdue' => (int) $tasks->clone()
                ->where('status', '!=', TaskStatus::Done->value)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->count(),
        ];

        $tickets = Ticket::where('assignee_id', $user->id)->with('customer')->latest()->limit(10)->get();
        $ticketCounts = [
            'open' => (int) Ticket::where('assignee_id', $user->id)->open()->count(),
            'total' => (int) Ticket::where('assignee_id', $user->id)->count(),
        ];

        $attendance = Attendance::where('user_id', $user->id)->latest('date')->limit(14)->get();

        return view('employees.show', [
            'employee' => $user,
            'performance' => $performance,
            'workload' => $workload,
            'tickets' => $tickets,
            'ticketCounts' => $ticketCounts,
            'attendance' => $attendance,
            'timelineEntries' => $timeline->entries($user, $timelineFrom, $timelineTo),
            'pending' => $timeline->pending($user),
            'timelineFromInput' => $request->string('from')->value() ?: now($displayTz)->toDateString(),
            'timelineToInput' => $request->string('to')->value() ?: now($displayTz)->toDateString(),
        ]);
    }
}
