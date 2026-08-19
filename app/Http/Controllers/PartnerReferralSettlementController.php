<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferralSettlementRequest;
use App\Models\Partner;
use App\Models\RecurringInvoice;
use App\Models\ReferralSettlement;
use App\Services\ReferralSettlementService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Staff-facing monthly collection entry + settle action for a Partner's
 * referred clients — mirrors PartnerController::markCommissionPaid()'s bare
 * "no live payment processing" simplicity, just for the new recurring,
 * per-client ReferralSettlement ledger instead of the one-time
 * PartnerCommissionStatement.
 */
class PartnerReferralSettlementController extends Controller
{
    /**
     * Record (or update) a PartnerCollects client's manually-reported
     * monthly collection for one recurring service — there is no NEDS
     * invoice for these clients, so this figure is the source of truth.
     */
    public function store(StoreReferralSettlementRequest $request, Partner $partner, ReferralSettlementService $service): RedirectResponse
    {
        $this->authorize('update', $partner);

        $template = RecurringInvoice::with('customer')->findOrFail($request->validated('recurring_invoice_id'));
        abort_unless($template->customer->referring_partner_id === $partner->id, 404);
        abort_unless($template->customer->isPartnerCollected(), 422, 'This client is not set up as Partner-collects.');

        $service->recordManualBilling(
            $template,
            Carbon::parse($request->validated('period_start')),
            Money::toPaise($request->validated('billed_amount')),
            $request->user(),
        );

        return back()->with('status', 'Monthly collection recorded.');
    }

    /**
     * Mark a settlement row settled — NEDS paid the partner (NedsCollects
     * rows) or the partner remitted NEDS (PartnerCollects rows), whichever
     * direction the row's own flow_mode is.
     */
    public function settle(Request $request, Partner $partner, ReferralSettlement $settlement, ReferralSettlementService $service): RedirectResponse
    {
        $this->authorize('update', $partner);
        abort_unless($settlement->partner_id === $partner->id, 404);

        $service->settle($settlement, $request->user());

        return back()->with('status', 'Settlement marked as settled.');
    }
}
