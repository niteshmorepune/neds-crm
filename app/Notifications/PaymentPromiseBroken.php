<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentPromiseBroken extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->invoice->customer?->company_name ?? 'Unknown';
        $balance = Money::format($this->invoice->balance());
        $promisedDate = $this->invoice->payment_promised_date?->format('d M Y') ?? '–';
        $number = $this->invoice->invoice_number ?: "Invoice #{$this->invoice->id}";

        return [
            'type' => 'payment_promise_broken',
            'invoice_id' => $this->invoice->id,
            'message' => "{$client} promised payment for {$number} by {$promisedDate} but {$balance} is still unpaid",
            'url' => route('invoices.show', $this->invoice->id),
        ];
    }
}
