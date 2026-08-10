<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Mail\PartnerInvitation;
use App\Models\Partner;
use App\Services\CollectionsMetrics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class PartnerController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Partner::class);

        $partners = Partner::orderBy('name')->get();

        return view('partners.index', compact('partners'));
    }

    public function show(Partner $partner, CollectionsMetrics $collectionsMetrics)
    {
        $this->authorize('view', $partner);

        $rows = $collectionsMetrics->clientHealth($partner->id);
        $billed = $collectionsMetrics->billedByClient($partner->id);
        $billedByMonth = $collectionsMetrics->billedByMonth($partner->id);

        return view('partners.show', compact('partner', 'rows', 'billed', 'billedByMonth'));
    }

    public function create()
    {
        $this->authorize('create', Partner::class);

        return view('partners.create');
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

        return view('partners.edit', compact('partner'));
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
}
