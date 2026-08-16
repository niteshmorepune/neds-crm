<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Mail\PartnerInvitation;
use App\Models\Customer;
use App\Models\Partner;
use App\Models\PartnerCommissionStatement;
use App\Services\CollectionsMetrics;
use App\Services\PartnerCommissionCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PartnerController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::orderBy('name')->get();

        return view('partners.index', compact('partners'));
    }

    public function show(Partner $partner, CollectionsMetrics $collectionsMetrics, PartnerCommissionCalculator $commissionCalculator)
    {
        $this->authorize('view', $partner);

        $rows = $collectionsMetrics->clientHealth($partner->id);
        $billed = $collectionsMetrics->billedByClient($partner->id);
        $billedByMonth = $collectionsMetrics->billedByMonth($partner->id);

        $commissionEstimate = $partner->hasCommission()
            ? $commissionCalculator->estimateForPartner($partner, now()->startOfMonth())
            : null;
        $commissionHistory = $partner->commissionStatements()->orderByDesc('period_start')->limit(12)->get();

        return view('partners.show', compact('partner', 'rows', 'billed', 'billedByMonth', 'commissionEstimate', 'commissionHistory'));
    }

    public function create()
    {
        $this->authorize('create', Partner::class);

        return view('partners.create', [
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function store(StorePartnerRequest $request)
    {
        $this->authorize('create', Partner::class);

        Partner::create($request->validated());

        return redirect()->route('partners.index')
            ->with('status', 'Partner added successfully.');
    }

    public function edit(Partner $partner)
    {
        $this->authorize('update', $partner);

        return view('partners.edit', [
            'partner' => $partner,
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner)
    {
        $this->authorize('update', $partner);

        $partner->update($request->validated());

        return redirect()->route('partners.index')
            ->with('status', 'Partner updated.');
    }

    public function destroy(Partner $partner)
    {
        $this->authorize('delete', $partner);

        $partner->delete();

        return redirect()->route('partners.index')
            ->with('status', 'Partner removed.');
    }

    /**
     * Grant partner portal access and email a set-password invitation.
     * Mirrors ContactsManager::invite() for the client portal.
     */
    public function invite(Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

        if (! $partner->email) {
            return back()->withErrors(['invite' => 'Add an email address before inviting this partner to the portal.']);
        }

        $token = $partner->inviteToPortal();
        Mail::to($partner->email)->send(new PartnerInvitation($partner, $token));

        return back()->with('status', 'Partner invited to the portal.');
    }

    public function revoke(Partner $partner): RedirectResponse
    {
        $this->authorize('update', $partner);

        $partner->revokePortalAccess();

        return back()->with('status', 'Partner portal access revoked.');
    }

    /**
     * Manual "mark as paid" ledger action — no live payment processing,
     * per the Tier 1 scoping decision.
     */
    public function markCommissionPaid(Request $request, Partner $partner, PartnerCommissionStatement $statement): RedirectResponse
    {
        $this->authorize('update', $partner);
        abort_unless($statement->partner_id === $partner->id, 404);

        $statement->update([
            'paid_at' => now(),
            'paid_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Commission marked as paid.');
    }
}
