<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Services\CollectionsMetrics;
use App\Services\PartnerCommissionCalculator;
use Illuminate\View\View;

class HomeController extends PartnerPortalController
{
    public function index(PartnerCommissionCalculator $commissionCalculator, CollectionsMetrics $collectionsMetrics): View
    {
        $partner = $this->partner();

        // Each referred client's own account summary — real invoices/
        // balances for a referral-only partner. For a reseller partner
        // (billing_customer_id set), every referred client's invoices are
        // GST-billed to the partner's own billingCustomer() instead, so
        // this is correctly empty per client; partnerAccount below carries
        // the real, consolidated figure in that case.
        $referredCustomers = $partner->referredCustomers()->orderBy('company_name')->get()
            ->map(fn ($customer) => (object) array_merge(
                ['customer' => $customer],
                $collectionsMetrics->accountSummaryForCustomer($customer)
            ));

        $partnerAccount = $partner->billing_customer_id !== null
            ? $collectionsMetrics->accountSummaryForCustomer($partner->billingCustomer) + ['customer' => $partner->billingCustomer]
            : null;

        $outstandingTotal = $partnerAccount
            ? $partnerAccount['outstanding_amount']
            : $referredCustomers->sum('outstanding_amount');
        $overdueClientCount = $partnerAccount
            ? ($partnerAccount['overdue_count'] > 0 ? 1 : 0)
            : $referredCustomers->filter(fn ($row) => $row->overdue_count > 0)->count();

        return view('partner-portal.home', [
            'partner' => $partner,
            'referredCustomers' => $referredCustomers,
            'partnerAccount' => $partnerAccount,
            'outstandingTotal' => $outstandingTotal,
            'overdueClientCount' => $overdueClientCount,
            'quotations' => $partner->quotations()->with('customer')->latest()->limit(50)->get(),
            'contentPieces' => $partner->contentPieces()->with('project')->latest()->get(),
            'commissionEstimate' => $partner->hasCommission()
                ? $commissionCalculator->estimateForPartner($partner, now()->startOfMonth())
                : null,
            'commissionHistory' => $partner->commissionStatements()->orderByDesc('period_start')->limit(12)->get(),
        ]);
    }
}
