<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\LeadAssignmentRuleRequest;
use App\Models\LeadAssignmentRule;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/manager management of Lead Assignment Rules — overrides the
 * least-loaded round-robin in LeadObserver::autoAssign() so a Meta ad
 * campaign (or a whole service line) can be routed to a specific Sales rep
 * instead of drifting to whoever's least loaded. See CLAUDE.md's decisions
 * log for the origin of this feature.
 */
class LeadAssignmentRuleController extends Controller
{
    public function index(): View
    {
        return view('lead-assignment-rules.index', [
            'rules' => LeadAssignmentRule::with(['service', 'assignedUser'])->latest()->get(),
            'services' => Service::active()->orderBy('sort_order')->get(),
            'salesUsers' => User::where('is_active', true)->where('role', UserRole::Sales->value)->orderBy('name')->get(),
        ]);
    }

    public function store(LeadAssignmentRuleRequest $request): RedirectResponse
    {
        LeadAssignmentRule::create($request->payload());

        return back()->with('status', 'Assignment rule added.');
    }

    public function update(LeadAssignmentRuleRequest $request, LeadAssignmentRule $leadAssignmentRule): RedirectResponse
    {
        $leadAssignmentRule->update($request->payload());

        return back()->with('status', 'Assignment rule updated.');
    }

    public function destroy(LeadAssignmentRule $leadAssignmentRule): RedirectResponse
    {
        abort_unless(auth()->user()->hasRole(UserRole::Admin, UserRole::Manager), 403);

        $leadAssignmentRule->delete();

        return back()->with('status', 'Assignment rule removed.');
    }
}
