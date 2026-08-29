<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Models\Customer;
use App\Services\CollectionsMetrics;
use App\Services\ReferralSettlementService;
use Illuminate\View\View;

class ClientController extends PartnerPortalController
{
    /**
     * A referred client's own account — quotations, invoices/receivables,
     * every recurring service/project (not just Active ones, so an On Hold
     * or Ended service is honestly visible too — see
     * partner-portal/clients/_services.blade.php), and the monthly
     * collection/settlement grid (see _monthly-collections.blade.php). Only
     * ever a directly referred client (its own referring_partner_id must
     * match), never a reseller partner's billingCustomer() — that
     * consolidated account is shown on the dashboard itself, not via this
     * per-client drill-down.
     */
    public function show(Customer $customer, CollectionsMetrics $collectionsMetrics, ReferralSettlementService $settlementService): View
    {
        abort_unless($customer->referring_partner_id === $this->partner()->id, 404);

        $customer->load([
            'recurringInvoices.service',
            'recurringInvoices.items',
            'recurringInvoices.invoices',
            'referralSettlements',
            'projects.service',
        ]);

        return view('partner-portal.clients.show', [
            'customer' => $customer,
            'account' => $collectionsMetrics->accountSummaryForCustomer($customer),
            // billingTarget() redirects to the real quotation owner when this
            // client is billed via a third party (Customer::billed_via_customer_id)
            // — unlike a reseller's shared consolidated account, a single
            // referred client's own quotations are unambiguous, so this is a
            // safe direct fix (no risk of pulling in another client's rows).
            'quotations' => $customer->billingTarget()->quotations()->latest()->get(),
            'settlementGrid' => $settlementService->gridForClient($customer),
        ]);
    }
}
