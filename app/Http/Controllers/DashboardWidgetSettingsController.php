<?php

namespace App\Http\Controllers;

use App\Models\HiddenDashboardWidget;
use App\Support\DashboardWidgets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard Customization (Manager panel doc Tier 3, scoped down via
 * AskUserQuestion to show/hide only — no reorder, no drag). Self-service:
 * any authenticated user manages their OWN widget visibility, no Policy —
 * reachable via a small link on the dashboard itself rather than a sidebar
 * item, same "settings page, not a menu module" precedent as Profile →
 * Google Account.
 */
class DashboardWidgetSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $panel = $user->dashboardPanel();
        $hidden = $user->hiddenDashboardWidgets()->pluck('widget_key')->all();

        return view('dashboard-widget-settings.edit', [
            'widgets' => DashboardWidgets::forPanel($panel),
            'hidden' => $hidden,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $panel = $user->dashboardPanel();
        $catalog = DashboardWidgets::forPanel($panel);

        // Checked boxes in the form = VISIBLE widgets. Anything in the
        // panel's catalog but not submitted is hidden. Validated against
        // the bounded catalog for this panel — never raw request input.
        $visibleKeys = collect($request->input('visible', []))
            ->filter(fn ($key) => array_key_exists($key, $catalog))
            ->all();
        $hiddenKeys = array_diff(array_keys($catalog), $visibleKeys);

        $user->hiddenDashboardWidgets()->whereIn('widget_key', array_keys($catalog))->delete();

        foreach ($hiddenKeys as $key) {
            HiddenDashboardWidget::create(['user_id' => $user->id, 'widget_key' => $key]);
        }

        return redirect()->route('dashboard')->with('status', 'Dashboard widgets updated.');
    }
}
