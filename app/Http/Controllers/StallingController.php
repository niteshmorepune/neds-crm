<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\StallReasonMetrics;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Phase 3 of the closure-guidance plan (2026-09-08, see CLAUDE.md) — every
 * Lead/Deal currently tagged with a stall_reason, in one place. Everyone
 * with menu.access:stalling sees their own book (a Lead by owner_id OR
 * telecaller_id, a Deal by owner_id — same scoping StallReasonMetrics/
 * ObjectionFollowUpDueSource already use, so this list and the Next
 * Action banner nudge can never disagree on what's stalling). Admin/
 * Manager additionally see the whole team's list and the reason-breakdown
 * rollup (a coaching signal, not something a rep needs about their own
 * peers) — gated inline, same convention as
 * ManagerActionCenterAttentionSource, no dedicated Policy class (matches
 * ClientRadar/FestivalController's own precedent: menu.access is the
 * whole gate).
 */
class StallingController extends Controller
{
    public function index(Request $request, StallReasonMetrics $metrics): View
    {
        $user = $request->user();
        $isManager = $user->hasRole(UserRole::Admin, UserRole::Manager);

        return view('stalling.index', [
            'mine' => $metrics->all($user->id),
            'isManager' => $isManager,
            'team' => $isManager ? $metrics->all() : null,
            'countsByReason' => $isManager ? $metrics->countsByReason() : null,
        ]);
    }
}
