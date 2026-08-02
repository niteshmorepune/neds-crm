<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Company-wide quick-access links (hosting panel, domain registrar, etc.).
 * Purely via menu.access:important-links — same convention as
 * ClientRadarController/FestivalController (no Policy class; the actual
 * add/edit/delete gating happens inside ImportantLinksManager, Admin/Manager
 * only). Per-client links live on the client page's own "Links" tab instead.
 */
class ImportantLinkController extends Controller
{
    public function index(): View
    {
        return view('important-links.index');
    }
}
