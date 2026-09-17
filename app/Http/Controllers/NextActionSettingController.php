<?php

namespace App\Http\Controllers;

use App\Models\NextActionSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin/Manager-only company-wide pause switch for the Next Action pop-up
 * (see NextActionEngine::nextFor()'s own check). Deliberately two explicit
 * actions (pause/resume) rather than one toggle, so a double-submit can't
 * flip the switch twice in a row unnoticed. Same no-Policy-class,
 * menu.access-middleware-only convention as Billing Settings/Festivals.
 */
class NextActionSettingController extends Controller
{
    public function index(): View
    {
        return view('next-action-settings.index', [
            'setting' => NextActionSetting::current()->load('updatedBy'),
        ]);
    }

    public function pause(): RedirectResponse
    {
        NextActionSetting::current()->update([
            'paused' => true,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('status', 'Next Action pop-ups paused for everyone.');
    }

    public function resume(): RedirectResponse
    {
        NextActionSetting::current()->update([
            'paused' => false,
            'updated_by' => auth()->id(),
        ]);

        return back()->with('status', 'Next Action pop-ups resumed for everyone.');
    }
}
