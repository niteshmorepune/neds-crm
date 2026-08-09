<?php

namespace App\Http\Controllers;

use App\Services\ProjectHealthMetrics;
use Illuminate\View\View;

/**
 * Manager panel doc's "Project Health Dashboard" (Tier 2 #03). See
 * ProjectHealthMetrics for the confirmed 🔴🟠🟡🟢 formula. Admin/manager
 * only, purely via menu.access:project-health — same convention as
 * ClientRadarController/FestivalController (no Policy class, no inline
 * role check).
 */
class ProjectHealthController extends Controller
{
    public function index(ProjectHealthMetrics $metrics): View
    {
        return view('project-health.index', [
            'rows' => $metrics->healthByProject(),
        ]);
    }
}
