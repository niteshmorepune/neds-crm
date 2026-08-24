<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\NewClientOnboardedNotification;

/**
 * Single point of truth for detecting "this is a client's first-ever
 * successful payment" and notifying Admin/Manager — called from every
 * place a Payment row actually gets created (InvoiceController::
 * storePayment(), ClientAdvanceController::apply(), RazorpayPaymentRecorder)
 * rather than duplicating the detection query at each call site. Doing this
 * in one place also means a future 4th payment path can't forget it.
 */
class NewClientOnboardingNotifier
{
    public function notifyIfFirstPayment(Invoice $invoice, Payment $payment): void
    {
        $customer = $invoice->customer;

        if ($customer === null) {
            return;
        }

        $hasEarlierPayment = Payment::whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('id', '!=', $payment->id)
            ->exists();

        if ($hasEarlierPayment) {
            return;
        }

        $notification = new NewClientOnboardedNotification($customer, $invoice, $payment);

        User::where('is_active', true)
            ->withAnyRole(UserRole::Admin, UserRole::Manager)
            ->get()
            ->each(fn (User $u) => $u->notify($notification));
    }
}
