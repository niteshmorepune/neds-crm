<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewInvoiceNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->invoice->customer?->company_name
            ?? Customer::where('id', $this->invoice->customer_id)->value('company_name')
            ?? 'Unknown';
        $amount = Money::format($this->invoice->total);
        $label = $this->invoice->invoice_number ?: 'Draft invoice';

        return [
            'type' => 'new_invoice',
            'invoice_id' => $this->invoice->id,
            'message' => "Invoice {$label} created for {$client} — {$amount}",
            'url' => route('invoices.show', $this->invoice->id),
        ];
    }
}
