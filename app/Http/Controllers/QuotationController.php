<?php

namespace App\Http\Controllers;

use App\Actions\ConvertQuotationToInvoice;
use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Jobs\SendQuotationWhatsAppJob;
use App\Mail\QuotationSent;
use App\Models\FollowUpReminder;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationAwaitingDecision;
use App\Notifications\QuotationNeedsApproval;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
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

        $quotation->load(['customer', 'items', 'deal', 'invoice', 'recurringInvoices']);

        return view('quotations.show', ['quotation' => $quotation]);
    }

    public function pdf(Quotation $quotation): Response
    {
        $this->authorize('view', $quotation);

        $quotation->load(['customer', 'items']);

        $pdf = Pdf::loadView('quotations.pdf', ['quotation' => $quotation]);

        $filename = $quotation->number
            ? str_replace('/', '-', $quotation->number).'.pdf'
            : 'quotation-'.$quotation->id.'.pdf';

        return $pdf->stream($filename);
    }

    public function send(Quotation $quotation): RedirectResponse
    {
        $this->authorize('view', $quotation);

        if ($quotation->needsApproval()) {
            return back()->withErrors(['send' => 'This quotation needs approval before it can be sent. Check the Approval Center or ask a manager to approve it.']);
        }

        $email = $quotation->customer->load('contacts')->billingEmail();

        if (! $email) {
            return back()->withErrors(['send' => 'No billing email found for this client.']);
        }

        Mail::to($email)->send(new QuotationSent($quotation->load(['customer', 'items'])));

        SendQuotationWhatsAppJob::dispatch($quotation->id);

        if ($quotation->status === QuotationStatus::Draft) {
            $quotation->update(['status' => QuotationStatus::Sent]);
        }

        $quotation->customer->portalContacts->each(
            fn ($contact) => $contact->notify(new QuotationAwaitingDecision($quotation))
        );

        $referringPartner = $quotation->customer->referringPartner;

        $nextAction = $referringPartner
            ? "Follow up with {$referringPartner->name} (referring partner) on quotation {$quotation->number} for {$quotation->customer->company_name}"
            : "Follow up on quotation {$quotation->number} sent to {$quotation->customer->company_name}";

        FollowUpReminder::create([
            'user_id' => auth()->id(),
            'customer_id' => $quotation->customer_id,
            'remind_at' => now()->addDays(3),
            'next_action' => $nextAction,
        ]);

        return back()->with('status', "Quotation sent to {$email}.");
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('review', $quotation);

        $quotation->update([
            'approval_status' => QuotationApprovalStatus::Approved,
            'approval_notes' => null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Quotation approved — it can now be sent.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('review', $quotation);

        $data = $request->validate(['approval_notes' => ['nullable', 'string', 'max:500']]);

        $quotation->update([
            'approval_status' => QuotationApprovalStatus::Rejected,
            'approval_notes' => $data['approval_notes'] ?? null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Quotation rejected.');
    }

    public function requestChanges(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('review', $quotation);

        $data = $request->validate(['approval_notes' => ['required', 'string', 'max:500']]);

        $quotation->update([
            'approval_status' => QuotationApprovalStatus::ChangesRequested,
            'approval_notes' => $data['approval_notes'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('status', 'Changes requested — the creator can edit and resubmit.');
    }

    public function resubmitForApproval(Request $request, Quotation $quotation): RedirectResponse
    {
        $this->authorize('update', $quotation);
        abort_if($quotation->approval_status === QuotationApprovalStatus::Approved, 409);

        $quotation->update([
            'approval_status' => QuotationApprovalStatus::Pending,
            'approval_notes' => null,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        User::withAnyRole(UserRole::Admin, UserRole::Manager)->get()
            ->each(fn (User $u) => $u->notify(new QuotationNeedsApproval($quotation)));

        return back()->with('status', 'Resubmitted for approval.');
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

        // Draft's only transition is to Sent — same approval gate send() enforces,
        // so this route can't be used to bypass it.
        if ($quotation->needsApproval()) {
            return back()->withErrors(['status' => 'This quotation needs approval before it can be sent. Check the Approval Center or ask a manager to approve it.']);
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
