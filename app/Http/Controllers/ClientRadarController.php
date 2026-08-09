<?php

namespace App\Http\Controllers;

use App\Services\ClientHealthMetrics;
use App\Services\ClientRadarService;
use Illuminate\View\View;

/**
 * At-risk / upsell signals for active clients (no contact in 14+ days,
 * declining touch activity, overdue invoices, single-service upsell
 * opportunities). Admin/manager only, purely via menu.access:client-radar —
 * same convention as FestivalController/ServiceController (no Policy class).
 *
 * Each row also carries its Client Health Score (Tier 2 #04 — see
 * ClientHealthMetrics), worst first, so the flagged list doubles as a
 * severity-ranked view instead of an unordered set of equally-weighted
 * clients.
 */
class ClientRadarController extends Controller
{
    public function index(ClientRadarService $radar, ClientHealthMetrics $health): View
    {
        $rows = $radar->flaggedClients()
            ->map(fn (array $row) => $row + ['score' => $health->scoreFor($row['flags'])])
            ->sortBy('score')
            ->values();

        return view('client-radar.index', [
            'rows' => $rows,
        ]);
    }
}
