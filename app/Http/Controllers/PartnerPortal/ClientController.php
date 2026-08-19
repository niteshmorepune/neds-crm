<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Models\Customer;
use App\Services\CollectionsMetrics;
use Illuminate\View\View;

class ClientController extends PartnerPortalController
{
    /**
     * A referred client's own account — quotations, invoices/receivables,
     * and every recurring service/project (not just Active ones, so an
     * On Hold or Ended service is honestly visible too — see
     * partner-portal/clients/_services.blade.php). Only ever a directly
     * referred client (its own referring_partner_id must match), never a
     * reseller partner's billingCustomer() — that consolidated account is
     * shown on the dashboard itself, not via this per-client drill-down.
     */
    public function show(Customer $customer, CollectionsMetrics $collectionsMetrics): View
    {
        abort_unless($customer->referring_partner_id === $this->partner()->id, 404);

        $customer->load([
            'recurringInvoices.service',
            'recurringInvoices.items',
            'recurringInvoices.invoices',
            'projects.service',
        ]);

        return view('partner-portal.clients.show', [
            'customer' => $customer,
            'account' => $collectionsMetrics->accountSummaryForCustomer($customer),
            'quotations' => $customer->quotations()->latest()->get(),
        ]);
    }
}
