<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Services\MarkdownGuideRenderer;
use Illuminate\View\View;

/**
 * Partner-facing FAQ, self-serve inside the partner portal — a sanitized
 * set of "how do I..." answers pulled from real partner questions, distinct
 * from docs/user-guides/partner-portal.md (the internal reference staff use
 * when onboarding a partner, not something a partner reads directly).
 */
class FaqController extends PartnerPortalController
{
    public function index(MarkdownGuideRenderer $renderer): View
    {
        $html = $renderer->render(base_path('docs/user-guides/faq-partner.md'));

        return view('partner-portal.faq', ['html' => $html]);
    }
}
