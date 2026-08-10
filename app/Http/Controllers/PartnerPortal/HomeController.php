<?php

namespace App\Http\Controllers\PartnerPortal;

use Illuminate\View\View;

class HomeController extends PartnerPortalController
{
    public function index(): View
    {
        $partner = $this->partner();

        return view('partner-portal.home', [
            'partner' => $partner,
            'referredCustomers' => $partner->referredCustomers()->orderBy('company_name')->get(),
            'contentPieces' => $partner->contentPieces()->with('project')->latest()->get(),
        ]);
    }
}
