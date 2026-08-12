<?php

namespace App\Http\Controllers\Portal;

use App\Services\MarkdownGuideRenderer;
use Illuminate\View\View;

/**
 * Client-facing FAQ, self-serve inside the portal — a sanitized set of
 * "how do I..." answers pulled from real client questions, distinct from
 * docs/user-guides/client-portal.md (the internal reference staff use to
 * onboard/support a client, not something a client reads directly).
 */
class FaqController extends PortalController
{
    public function index(MarkdownGuideRenderer $renderer): View
    {
        $html = $renderer->render(base_path('docs/user-guides/faq-client.md'));

        return view('portal.faq', ['html' => $html]);
    }
}
