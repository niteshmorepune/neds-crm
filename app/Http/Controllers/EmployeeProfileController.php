<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use App\Services\ReportMetrics;
use Illuminate\Http\Request;
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
 * there, unchanged by this). No UserPolicy exists in this app (confirmed
 * before building) — every user-management check is inline abort_unless,
 * so this follows that same convention rather than introducing one just
 * for this feature.
 */
class EmployeeProfileController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeViewer($request);

        $employees = User::where('is_active', true)->orderBy('name')->get();

        return view('employees.index', compact('employees'));
    }

    public function show(Request $request, User $user, ReportMetrics $metrics): View
    {
        $this->authorizeViewer($request);

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();

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
        ]);
    }

    private function authorizeViewer(Request $request): void
    {
        abort_unless($request->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);
    }
}
