<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Models\Quotation;
use App\Services\PartnerCommissionCalculator;
use Illuminate\View\View;

class HomeController extends PartnerPortalController
{
    public function index(PartnerCommissionCalculator $commissionCalculator): View
    {
        $partner = $this->partner();

        return view('partner-portal.home', [
            'partner' => $partner,
            'referredCustomers' => $partner->referredCustomers()->orderBy('company_name')->get(),
            'quotations' => Quotation::query()
                ->whereHas('customer', fn ($q) => $q->where('referring_partner_id', $partner->id))
                ->with('customer')
                ->latest()
                ->limit(50)
                ->get(),
            'contentPieces' => $partner->contentPieces()->with('project')->latest()->get(),
            'commissionEstimate' => $partner->hasCommission()
                ? $commissionCalculator->estimateForPartner($partner, now()->startOfMonth())
                : null,
            'commissionHistory' => $partner->commissionStatements()->orderByDesc('period_start')->limit(12)->get(),
        ]);
    }
}
