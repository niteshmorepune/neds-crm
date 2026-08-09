<?php

namespace App\Http\Controllers;

use App\Services\RevenueAtRiskMetrics;
use Illuminate\View\View;

/**
 * Manager panel doc's "Revenue at Risk" (Tier 2 #10). See
 * RevenueAtRiskMetrics for what this aggregates and why the total isn't
 * deduplicated across buckets. Admin/manager only, purely via
 * menu.access:revenue-at-risk — same convention as
 * ClientRadarController/FestivalController (no Policy class, no inline
 * role check).
 */
class RevenueAtRiskController extends Controller
{
    public function index(RevenueAtRiskMetrics $metrics): View
    {
        return view('revenue-at-risk.index', [
            'signals' => $metrics->signals(),
            'total' => $metrics->totalAtRisk(),
        ]);
    }
}
