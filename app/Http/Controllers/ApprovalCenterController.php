<?php

namespace App\Http\Controllers;

use App\Services\ApprovalCenterMetrics;
use Illuminate\View\View;

/**
 * Admin/manager only, purely via menu.access:approval-center — same
 * convention as ManagerActionCenterController (no Policy class, no inline
 * role check; MenuResolver::canAccess() is the real gate).
 */
class ApprovalCenterController extends Controller
{
    public function index(ApprovalCenterMetrics $metrics): View
    {
        return view('approval-center.index', [
            'leaveRequests' => $metrics->pendingLeaveRequests(),
            'workFromHomeRequests' => $metrics->pendingWorkFromHomeRequests(),
            'quotations' => $metrics->pendingQuotations(),
            'projectsWithUpdates' => $metrics->projectsWithPendingUpdates(),
            'totalCount' => $metrics->totalCount(),
        ]);
    }
}
