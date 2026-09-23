<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\LeadAssignmentSettingRequest;
use App\Models\LeadAssignmentSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/Manager-only company-wide "force all new leads to one Sales rep"
 * switch — checked first in LeadObserver::autoAssign(), ahead of
 * LeadAssignmentRule matching and the least-loaded round-robin fallback.
 * Deliberately two explicit actions (enable/disable) rather than one
 * toggle, so a double-submit can't flip it twice unnoticed — same shape
 * as NextActionSettingController's pause/resume.
 */
class LeadAssignmentSettingController extends Controller
{
    public function index(): View
    {
        return view('lead-assignment-settings.index', [
            'setting' => LeadAssignmentSetting::current()->load(['forcedUser', 'updatedBy']),
            'salesUsers' => User::where('is_active', true)->where('role', UserRole::Sales->value)->orderBy('name')->get(),
        ]);
    }

    public function enable(LeadAssignmentSettingRequest $request): RedirectResponse
    {
        $forcedUser = User::findOrFail($request->validated('forced_user_id'));

        LeadAssignmentSetting::current()->update([
            'enabled' => true,
            'forced_user_id' => $forcedUser->id,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('status', "All new leads will now be assigned to {$forcedUser->name}.");
    }

    public function disable(): RedirectResponse
    {
        LeadAssignmentSetting::current()->update([
            'enabled' => false,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('status', 'New leads are back to normal assignment rules.');
    }
}
