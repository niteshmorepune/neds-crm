<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Services\CollectionsMetrics;
use App\Services\PartnerCommissionCalculator;
use App\Services\ReferralSettlementService;
use Illuminate\View\View;

class HomeController extends PartnerPortalController
{
    public function index(PartnerCommissionCalculator $commissionCalculator, CollectionsMetrics $collectionsMetrics, ReferralSettlementService $settlementService): View
    {
        $partner = $this->partner();

        // recurringInvoices eager-loaded for the "services on hold" portfolio
        // count below — nonOrphanedRecurringInvoices() operates on the
        // already-loaded collection, so this avoids an N+1 across her whole
        // referred-client list.
        $referredCustomerModels = $partner->referredCustomers()
            ->with('recurringInvoices.invoices')
            ->orderBy('company_name')
            ->get();

        // Each referred client's own account summary — real invoices/
        // balances for a referral-only partner. For a reseller partner
        // (billing_customer_id set), every referred client's invoices are
        // GST-billed to the partner's own billingCustomer() instead, so
        // this is correctly empty per client; partnerAccount below carries
        // the real, consolidated figure in that case.
        $referredCustomers = $referredCustomerModels
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

        // Portfolio-level summary strip: client status breakdown, how many
        // recurring services are currently On Hold across every referred
        // client (a reseller's consolidated billing doesn't change whether
        // an individual client's own service is paused), and where her
        // quotations stand — over the FULL set, not the 50-row display list
        // below, so this count never quietly undercounts a heavy referrer.
        $clientStatusCounts = $referredCustomerModels->countBy(fn ($c) => $c->status->value);
        $servicesOnHoldCount = $referredCustomerModels->sum(
            fn ($c) => $c->nonOrphanedRecurringInvoices()->filter(fn ($r) => $r->dashboardStatus(false) === 'on_hold')->count()
        );
        $quotationStatusCounts = $partner->quotations()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $settlementNetPosition = $settlementService->portfolioNetPosition($partner->referralSettlements);

        return view('partner-portal.home', [
            'partner' => $partner,
            'referredCustomers' => $referredCustomers,
            'partnerAccount' => $partnerAccount,
            'outstandingTotal' => $outstandingTotal,
            'overdueClientCount' => $overdueClientCount,
            'clientStatusCounts' => $clientStatusCounts,
            'servicesOnHoldCount' => $servicesOnHoldCount,
            'quotationStatusCounts' => $quotationStatusCounts,
            'settlementNetPosition' => $settlementNetPosition,
            'quotations' => $partner->quotations()->with('customer')->latest()->limit(50)->get(),
            'contentPieces' => $partner->contentPieces()->with('project')->latest()->get(),
            'commissionEstimate' => $partner->hasCommission()
                ? $commissionCalculator->estimateForPartner($partner, now()->startOfMonth())
                : null,
            'commissionHistory' => $partner->commissionStatements()->orderByDesc('period_start')->limit(12)->get(),
        ]);
    }
}
