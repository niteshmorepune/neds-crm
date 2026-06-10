<?php

namespace App\Http\Controllers;

use App\Actions\ConvertQuotationToInvoice;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Quotation::class);

        $user = $request->user();

        $quotations = Quotation::query()
            ->with('customer')
            ->when($user->hasRole(UserRole::Sales)
                && ! $user->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Accounts),
                fn ($q) => $q->whereHas('customer', fn ($c) => $c->visibleTo($user)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('quotations.index', [
            'quotations' => $quotations,
            'statuses' => QuotationStatus::cases(),
            'filters' => $request->only('status'),
        ]);
    }

    public function show(Quotation $quotation): View
    {
        $this->authorize('view', $quotation);

        $quotation->load(['customer', 'items', 'deal', 'invoice']);

        return view('quotations.show', ['quotation' => $quotation]);
    }

    public function transition(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('view', $quotation);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(QuotationStatus::class)],
        ]);

        $target = QuotationStatus::from($validated['status']);

        if (! $quotation->status->canTransitionTo($target)) {
            return back()->withErrors(['status' => "Cannot move a {$quotation->status->label()} quotation to {$target->label()}."]);
        }

        $quotation->update(['status' => $target]);

        return back()->with('status', "Quotation marked {$target->label()}.");
    }

    public function convert(Quotation $quotation, ConvertQuotationToInvoice $converter): RedirectResponse
    {
        $this->authorize('convert', $quotation);

        if ($quotation->status !== QuotationStatus::Accepted) {
            return back()->withErrors(['convert' => 'Only an accepted quotation can be converted.']);
        }

        if ($quotation->invoice()->exists()) {
            return redirect()->route('invoices.show', $quotation->invoice)->with('status', 'Already invoiced.');
        }

        $invoice = $converter->handle($quotation);

        return redirect()->route('invoices.show', $invoice)->with('status', 'Invoice created from quotation.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $this->authorize('delete', $quotation);

        $quotation->items()->delete();
        $quotation->delete();

        return redirect()->route('quotations.index')->with('status', 'Quotation deleted.');
    }
}
