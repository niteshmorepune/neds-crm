<?php

namespace App\Http\Controllers;

use App\Services\TaskWorkloadMetrics;
use Illuminate\View\View;

/**
 * Manager panel doc's "Team Workload & Capacity" (Tier 2 #02). Formula
 * confirmed with the owner via AskUserQuestion, 2026-08-09: a person is
 * "overloaded" when their open (not-Done) task count exceeds 1.5x their
 * role's own average, OR they have 3+ overdue tasks — see
 * TaskWorkloadMetrics::workloadByUser() for the exact computation, shared
 * with the Emptask team summary table so the two pages' task counts can
 * never disagree.
 *
 * Admin/manager only, purely via menu.access:team-workload — same
 * convention as ClientRadarController/FestivalController (no Policy class,
 * no inline role check).
 */
class TeamWorkloadController extends Controller
{
    public function index(TaskWorkloadMetrics $metrics): View
    {
        return view('team-workload.index', [
            'rows' => $metrics->workloadByUser(),
        ]);
    }
}
