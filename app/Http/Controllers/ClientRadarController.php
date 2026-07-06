<?php

namespace App\Http\Controllers;

use App\Services\ClientRadarService;
use Illuminate\View\View;

/**
 * At-risk / upsell signals for active clients (no contact in 14+ days,
 * declining touch activity, overdue invoices, single-service upsell
 * opportunities). Admin/manager only, purely via menu.access:client-radar —
 * same convention as FestivalController/ServiceController (no Policy class).
 */
class ClientRadarController extends Controller
{
    public function index(ClientRadarService $radar): View
    {
        return view('client-radar.index', [
            'rows' => $radar->flaggedClients(),
        ]);
    }
}
