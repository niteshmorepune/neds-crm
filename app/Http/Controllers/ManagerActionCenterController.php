<?php

namespace App\Http\Controllers;

use App\Services\ManagerActionCenterMetrics;
use Illuminate\View\View;

/**
 * Manager panel doc's "Action Center" — see ManagerActionCenterMetrics for
 * what it does and deliberately doesn't. Admin/manager only, purely via
 * menu.access:manager-action-center — same convention as
 * ClientRadarController/FestivalController (no Policy class, no inline
 * role check; MenuResolver::canAccess() is role-based and IS the real
 * gate here, not a cosmetic one — see EmployeeProfileController's note on
 * the same pattern).
 */
class ManagerActionCenterController extends Controller
{
    public function index(ManagerActionCenterMetrics $metrics): View
    {
        return view('manager-action-center.index', [
            'signals' => $metrics->signals(),
            'followUps' => $metrics->pendingFollowUps(),
            'followUpCount' => $metrics->pendingFollowUpCount(),
        ]);
    }
}
