<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\DashboardMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardMetrics $metrics): View
    {
        $user = $request->user();

        // Admin & manager get the full company dashboard; everyone else gets a
        // role-focused panel. Common widgets (attendance, daily report) render
        // for all from the parent view. Deliberately keyed on the PRIMARY
        // role only ($user->role, not hasRole()) — an additional role never
        // changes which dashboard panel someone lands on, matching the
        // sidebar's primary-role-only behavior (see CLAUDE.md decisions log).
        [$panel, $data] = match (true) {
            in_array($user->role, [UserRole::Admin, UserRole::Manager], true) => ['admin', [
                'stats' => $metrics->adminStats(),
                'services' => $metrics->servicesOverview(),
                'tasks' => $metrics->taskSummary(),
            ]],
            $user->role === UserRole::Sales => ['sales', ['stats' => $metrics->salesStats($user)]],
            $user->role === UserRole::Accounts => ['accounts', ['stats' => $metrics->accountsStats()]],
            $user->role === UserRole::Support => ['support', ['stats' => $metrics->supportStats($user)]],
            $user->role === UserRole::Intern => ['intern', ['stats' => $metrics->internStats($user)]],
            default => ['blank', []],
        };

        return view('dashboard', ['panel' => $panel, 'panelData' => $data]);
    }
}
