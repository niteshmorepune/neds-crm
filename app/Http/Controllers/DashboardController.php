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
        // for all from the parent view.
        [$panel, $data] = match (true) {
            $user->hasRole(UserRole::Admin, UserRole::Manager) => ['admin', [
                'stats' => $metrics->adminStats(),
                'services' => $metrics->servicesOverview(),
                'tasks' => $metrics->taskSummary(),
            ]],
            $user->hasRole(UserRole::Sales) => ['sales', ['stats' => $metrics->salesStats($user)]],
            $user->hasRole(UserRole::Accounts) => ['accounts', ['stats' => $metrics->accountsStats()]],
            $user->hasRole(UserRole::Support) => ['support', ['stats' => $metrics->supportStats($user)]],
            $user->hasRole(UserRole::Intern) => ['intern', ['stats' => $metrics->internStats($user)]],
            default => ['blank', []],
        };

        return view('dashboard', ['panel' => $panel, 'panelData' => $data]);
    }
}
