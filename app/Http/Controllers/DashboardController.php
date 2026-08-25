<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\ApprovalCenterMetrics;
use App\Services\CallPriorityService;
use App\Services\CollectionsMetrics;
use App\Services\DashboardMetrics;
use App\Services\ProjectHealthMetrics;
use App\Services\RoleTargetMetrics;
use App\Support\DashboardWidgets;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics, CallPriorityService $callPriority, RoleTargetMetrics $roleTargets, ApprovalCenterMetrics $approvalCenter, ProjectHealthMetrics $projectHealth, CollectionsMetrics $collections): View
    {
        $user = $request->user();
        $announcements = Announcement::active()->forStaff()->newestFirst()->get();

        // Global Dashboard Filters: a single ?month= filter, same
        // regex-degrades-to-null validation as Leads/Recurring Invoices
        // (malformed input is silently treated as "no filter", never a
        // validation error). Only threaded into salesStats()/accountsStats()
        // — the only two dashboard figures across every role that are
        // genuinely period-scoped ("this month") rather than point-in-time
        // snapshots (see DashboardMetrics' own docblocks on each). The
        // month picker itself only renders on the Sales/Accounts partials
        // for the same reason — nothing on the other partials would
        // actually respond to it.
        $monthParam = $this->validMonth($request);
        $selectedMonth = $monthParam ? Carbon::createFromFormat('Y-m', $monthParam) : now();

        // Admin & manager get the full company dashboard; everyone else gets a
        // role-focused panel. Common widgets (attendance, daily report) render
        // for all from the parent view. Deliberately keyed on the PRIMARY
        // role only via User::dashboardPanel(), matching the sidebar's
        // primary-role-only behavior (see CLAUDE.md decisions log).
        $panel = $user->dashboardPanel();

        $data = match ($panel) {
            'admin' => [
                'stats' => $metrics->adminStats(),
                'services' => $metrics->servicesOverview(),
                'tasks' => $metrics->taskSummary(),
                'pendingApprovals' => $approvalCenter->totalCount(),
                'ongoingProjects' => $projectHealth->healthByProject(),
                'upcomingPayments' => $collections->upcomingPaymentsAndRenewals(),
            ],
            'sales' => [
                'stats' => $metrics->salesStats($user, $selectedMonth),
                'callPriority' => $callPriority->rankedClients($user)->all(),
                'month' => $selectedMonth->format('Y-m'),
            ],
            'accounts' => [
                'stats' => $metrics->accountsStats($selectedMonth),
                'month' => $selectedMonth->format('Y-m'),
                'targetProgress' => $roleTargets->progressForUser($user),
            ],
            'support' => ['stats' => $metrics->supportStats($user), 'targetProgress' => $roleTargets->progressForUser($user)],
            'intern' => ['stats' => $metrics->internStats($user), 'targetProgress' => $roleTargets->progressForUser($user)],
            'telecaller' => ['stats' => $metrics->telecallerStats($user), 'targetProgress' => $roleTargets->progressForUser($user)],
            default => [],
        };

        // Dashboard Customization (show/hide only): every widget in this
        // panel's catalog is visible unless the user has explicitly hidden
        // it. $visibleWidgets is checked with in_array() by each partial —
        // see App\Support\DashboardWidgets for the full catalog.
        $hidden = $user->hiddenDashboardWidgets()->pluck('widget_key')->all();
        $visibleWidgets = array_values(array_diff(array_keys(DashboardWidgets::forPanel($panel)), $hidden));

        $data['visibleWidgets'] = $visibleWidgets;

        return view('dashboard', ['panel' => $panel, 'panelData' => $data, 'announcements' => $announcements]);
    }

    private function validMonth(Request $request): ?string
    {
        $month = $request->string('month')->trim()->value();

        return ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) ? $month : null;
    }
}
