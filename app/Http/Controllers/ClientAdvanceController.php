<?php

namespace App\Http\Controllers;

use App\Enums\ClientAdvanceStatus;
use App\Enums\UserRole;
use App\Http\Requests\ClientAdvanceApplyRequest;
use App\Http\Requests\ClientAdvanceStoreRequest;
use App\Models\ClientAdvance;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Notifications\ClientAdvanceRecorded;
use App\Notifications\PaymentRecordedNotification;
use App\Services\NewClientOnboardingNotifier;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ClientAdvanceController extends Controller
{
    public function store(ClientAdvanceStoreRequest $request, Customer $client): RedirectResponse
    {
        $this->authorize('create', ClientAdvance::class);

        $advance = $client->clientAdvances()->create([
            'amount' => Money::toPaise($request->validated()['amount']),
            'received_on' => $request->validated()['received_on'],
            'mode' => $request->validated()['mode'],
            'reference' => $request->validated()['reference'] ?? null,
            'note' => $request->validated()['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);

        $this->notifyAdvanceRecorded($advance, $request->user());

        return redirect()->route('clients.show', $client)->with('status', 'Advance recorded.');
    }

    public function apply(ClientAdvanceApplyRequest $request, Invoice $invoice, ClientAdvance $advance, NewClientOnboardingNotifier $onboardingNotifier): RedirectResponse
    {
        $this->authorize('apply', $advance);
        abort_if($advance->customer_id !== $invoice->customer_id, 404);

        $requested = Money::toPaise($request->validated()['amount']);
        $cap = min($advance->remaining(), $invoice->balance());

        if ($requested > $cap) {
            return back()->withErrors(['amount' => 'Amount exceeds the advance remaining ('.Money::format($advance->remaining()).') or the invoice balance ('.Money::format($invoice->balance()).').']);
        }

        $payment = $invoice->payments()->create([
            'paid_on' => now()->toDateString(),
            'mode' => $advance->mode,
            'reference' => 'Advance #'.$advance->id.($advance->reference ? " ({$advance->reference})" : ''),
            'amount' => $requested,
            'tds_amount' => 0,
            'recorded_by' => $request->user()->id,
            'client_advance_id' => $advance->id,
        ]);

        $invoice->refreshPaymentStatus();
        $advance->refreshAppliedStatus();
        $onboardingNotifier->notifyIfFirstPayment($invoice, $payment);

        $recorder = $request->user();
        $notification = new PaymentRecordedNotification($invoice, $payment);
        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Accounts)
            ->where('id', '!=', $recorder->id)
            ->get();
        $ownerId = Customer::where('id', $invoice->customer_id)->value('owner_id');
        if ($ownerId && $ownerId !== $recorder->id) {
            $owner = User::find($ownerId);
            if ($owner && ! $recipients->contains('id', $owner->id)) {
                $recipients = $recipients->push($owner);
            }
        }
        $recipients->each(fn (User $u) => $u->notify($notification));

        return redirect()->route('invoices.show', $invoice)->with('status', 'Advance applied to invoice.');
    }

    public function cancel(ClientAdvance $advance): RedirectResponse
    {
        $this->authorize('cancel', $advance);
        abort_unless($advance->amount_applied === 0, 403, 'This advance already has payments applied — it can\'t be cancelled.');

        $advance->update(['status' => ClientAdvanceStatus::Cancelled]);

        return redirect()->route('clients.show', $advance->customer_id)->with('status', 'Advance cancelled.');
    }

    public function index(): View
    {
        $this->authorize('viewAny', ClientAdvance::class);

        $advances = ClientAdvance::query()
            ->with('customer')
            ->whereIn('status', [ClientAdvanceStatus::Outstanding, ClientAdvanceStatus::PartiallyApplied])
            ->latest('received_on')
            ->paginate(20);

        return view('advances.index', ['advances' => $advances]);
    }

    private function notifyAdvanceRecorded(ClientAdvance $advance, User $recorder): void
    {
        $notification = new ClientAdvanceRecorded($advance);
        $recipients = User::where('is_active', true)
            ->withAnyRole(UserRole::Accounts)
            ->where('id', '!=', $recorder->id)
            ->get();
        $ownerId = $advance->customer?->owner_id;
        if ($ownerId && $ownerId !== $recorder->id) {
            $owner = User::find($ownerId);
            if ($owner && ! $recipients->contains('id', $owner->id)) {
                $recipients = $recipients->push($owner);
            }
        }
        $recipients->each(fn (User $u) => $u->notify($notification));
    }
}
