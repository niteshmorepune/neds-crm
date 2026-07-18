<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Deal;
use App\Services\SalesPipelineMetrics;
use Illuminate\View\View;

class SalesDashboardController extends Controller
{
    public function __construct(private readonly SalesPipelineMetrics $metrics) {}

    public function index(): View
    {
        $user = auth()->user();
        abort_unless($user->can('viewAny', Deal::class), 403);

        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);
        $kpis = $this->metrics->kpis($user);

        return view('sales-dashboard.index', [
            'kpis' => $kpis,
            'stageConversion' => $this->metrics->stageConversion($user),
            'trend' => $this->metrics->wonValueTrend($user),
            'targetProgress' => $this->metrics->targetProgress($user, $kpis),
            'serviceBreakdown' => $this->metrics->serviceBreakdown($user),
            'needsAttention' => $this->metrics->needsAttention($user),
            'leaderboard' => $isManager ? $this->metrics->repLeaderboard() : null,
            'suggestedTargets' => $isManager ? $this->metrics->suggestedTargets() : null,
            'isManager' => $isManager,
        ]);
    }
}
