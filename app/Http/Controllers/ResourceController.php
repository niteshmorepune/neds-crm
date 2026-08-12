<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * "Resources" — Files (TeamResource) + Links (ImportantLink) on one page,
 * two tabs. Purely via menu.access:important-links (key deliberately kept
 * unchanged even though the page moved — see MenuItemsSeeder comment).
 * Replaces the old standalone ImportantLinkController.
 */
class ResourceController extends Controller
{
    public function index(): View
    {
        return view('resources.index');
    }
}
