<?php

namespace App\Http\Controllers\Portal;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationDecisionRecorded;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuotationController extends PortalController
{
    public function index(): View
    {
        $quotations = $this->customer()
            ->quotations()
            ->with('items')
            ->latest()
            ->paginate(15);

        return view('portal.quotations.index', compact('quotations'));
    }

    public function show(int $quotation): View
    {
        // Scoped to the contact's customer — findOrFail 404s on another's quotation.
        $quotation = $this->customer()->quotations()->with('items')->findOrFail($quotation);

        return view('portal.quotations.show', compact('quotation'));
    }

    public function accept(int $quotation): RedirectResponse
    {
        $quotation = $this->customer()->quotations()->findOrFail($quotation);

        return $this->decide($quotation, QuotationStatus::Accepted, null);
    }

    public function reject(Request $request, int $quotation): RedirectResponse
    {
        $quotation = $this->customer()->quotations()->findOrFail($quotation);

        $data = $request->validate(['client_decision_note' => ['nullable', 'string', 'max:1000']]);

        return $this->decide($quotation, QuotationStatus::Rejected, $data['client_decision_note'] ?? null);
    }

    private function decide(Quotation $quotation, QuotationStatus $target, ?string $note): RedirectResponse
    {
        if (! $quotation->status->canTransitionTo($target)) {
            return back()->withErrors(['status' => "This quotation is {$quotation->status->label()} and can no longer be {$target->label()}."]);
        }

        $quotation->update([
            'status' => $target,
            'client_decision_note' => $note,
        ]);

        if ($ownerId = $quotation->ownerId()) {
            User::find($ownerId)?->notify(new QuotationDecisionRecorded($quotation));
        }

        return redirect()->route('portal.quotations.show', $quotation)
            ->with('status', "Quotation marked {$target->label()}.");
    }
}
