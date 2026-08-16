<?php

namespace App\Http\Controllers\PartnerPortal;

use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Services\CollectionsMetrics;
use Illuminate\View\View;

class ClientController extends PartnerPortalController
{
    /**
     * A referred client's own account — quotations, invoices/receivables,
     * and active projects. Only ever a directly referred client (its own
     * referring_partner_id must match), never a reseller partner's
     * billingCustomer() — that consolidated account is shown on the
     * dashboard itself, not via this per-client drill-down.
     */
    public function show(Customer $customer, CollectionsMetrics $collectionsMetrics): View
    {
        abort_unless($customer->referring_partner_id === $this->partner()->id, 404);

        return view('partner-portal.clients.show', [
            'customer' => $customer,
            'account' => $collectionsMetrics->accountSummaryForCustomer($customer),
            'quotations' => $customer->quotations()->latest()->get(),
            'projects' => $customer->projects()->where('status', ProjectStatus::Active->value)->get(),
        ]);
    }
}
