<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RecurringInvoiceDueSoon extends Notification
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
        $dueDate = $this->invoice->due_date?->format('d M Y') ?? '–';
        $number = $this->invoice->invoice_number ?: "Invoice #{$this->invoice->id}";

        return [
            'type' => 'recurring_invoice_due_soon',
            'invoice_id' => $this->invoice->id,
            'message' => "Recurring invoice {$number} for {$client} is due on {$dueDate} — {$amount}",
            'url' => route('invoices.show', $this->invoice->id),
        ];
    }
}
