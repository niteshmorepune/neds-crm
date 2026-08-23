<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\VisibilityAuditFunnelMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The recovery queue for the Visibility Audit funnel — Leads attributably
 * stuck at the landing page or checkout stage with no completed payment,
 * so Sales/Telecaller have a concrete list to work rather than needing to
 * remember to check. Gated the same as the Lead Generation page itself
 * (same menu key, same policy) since it's just another view over Leads,
 * not a separate access concern.
 *
 * "Your Message Log" / "Your Gaps" below the existing (whole-team, unscoped)
 * tables are personal to whoever's viewing — a plain, non-AI list of the
 * viewer's own leads only, so a Sales user gets the same "which template
 * went out" and "what's stuck" visibility Admin/Manager already have on the
 * separate reports dashboard, without opening that page (and its
 * whole-funnel analytics/AI panel) up to them. See CLAUDE.md's decisions
 * log for why this stayed a plain list rather than a second AI call.
 */
class VisibilityAuditRecoveryController extends Controller
{
    public function index(Request $request, VisibilityAuditFunnelMetrics $metrics): View
    {
        $this->authorize('viewAny', Lead::class);

        $userId = $request->user()->id;

        return view('leads.visibility-audit-recovery', [
            'stuckAtLanding' => $metrics->stuckAtLanding(),
            'stuckAtCheckout' => $metrics->stuckAtCheckout(),
            'funnel' => $metrics->funnelSummary(),
            'myMessageLog' => $metrics->touchLogQuery(ownerId: $userId)->limit(20)->get(),
            'myAwaitingServiceTag' => $metrics->leadsAwaitingServiceTag(10, $userId),
            'myStuckAtCheckout' => $metrics->needsFollowUp($metrics->stuckAtCheckout($userId)),
            'myStuckAtLanding' => $metrics->needsFollowUp($metrics->stuckAtLanding($userId)),
            'myUnansweredReplies' => $metrics->unansweredInboundReplies(now()->subHours(2), $userId),
        ]);
    }
}
