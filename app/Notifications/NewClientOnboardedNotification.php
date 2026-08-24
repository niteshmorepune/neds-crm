<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Fired once per client, on the first successful payment ever recorded
 * against any of their invoices — see NewClientOnboardingNotifier, the
 * single point of truth for "is this genuinely the first one" shared by
 * every payment-recording path (staff-recorded, client-advance applied,
 * Razorpay gateway). Distinct from the existing generic
 * PaymentRecordedNotification (Accounts + the client's owner, fires on
 * every payment) — this one goes to Admin/Manager and marks a one-time
 * milestone, not routine collections.
 *
 * Deliberately does NOT store 'invoice_id' in the payload — that key
 * triggers NotificationController's "(invoice deleted)" relabeling
 * globally by key name, but this notification's link points at the client
 * profile, not the invoice, so it must stay valid even after that specific
 * invoice is later deleted/corrected.
 */
class NewClientOnboardedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Customer $customer,
        public Invoice $invoice,
        public Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $services = $this->customer->activeServiceNames();
        $serviceLabel = $services->isNotEmpty() ? $services->implode(', ') : 'General';
        $amount = Money::format($this->payment->amount);
        $paidOn = $this->payment->paid_on?->format('d M Y');

        $message = "New client onboarded: {$this->customer->company_name} — {$serviceLabel} — {$amount} paid"
            .($paidOn ? " on {$paidOn}" : '');

        return [
            'type' => 'new_client_onboarded',
            'customer_id' => $this->customer->id,
            'related_invoice_id' => $this->invoice->id,
            'payment_id' => $this->payment->id,
            'services' => $services->all(),
            'amount' => $this->payment->amount,
            'paid_on' => $this->payment->paid_on?->toDateString(),
            'assigned_employee' => $this->customer->owner?->name,
            'message' => $message,
            'url' => route('clients.show', $this->customer),
        ];
    }
}
