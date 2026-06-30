<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRecordedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public Payment $payment,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->invoice->customer?->company_name
            ?? Customer::where('id', $this->invoice->customer_id)->value('company_name')
            ?? 'Unknown';
        $amount = Money::format($this->payment->amount);
        $number = $this->invoice->invoice_number ?: "Invoice #{$this->invoice->id}";

        return [
            'type' => 'payment_recorded',
            'invoice_id' => $this->invoice->id,
            'payment_id' => $this->payment->id,
            'message' => "Payment of {$amount} recorded on {$number} for {$client}",
            'url' => route('invoices.show', $this->invoice->id),
        ];
    }
}
