<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\View\View;

/**
 * The recovery queue for the Visibility Audit funnel — Leads attributably
 * stuck at the landing page or checkout stage with no completed payment,
 * so Sales/Telecaller have a concrete list to work rather than needing to
 * remember to check. Gated the same as the Lead Generation page itself
 * (same menu key, same policy) since it's just another view over Leads,
 * not a separate access concern.
 */
class VisibilityAuditRecoveryController extends Controller
{
    public function index(VisibilityAuditFunnelMetrics $metrics): View
    {
        $this->authorize('viewAny', Lead::class);

        return view('leads.visibility-audit-recovery', [
            'stuckAtLanding' => $metrics->stuckAtLanding(),
            'stuckAtCheckout' => $metrics->stuckAtCheckout(),
            'funnel' => $metrics->funnelSummary(),
        ]);
    }
}
