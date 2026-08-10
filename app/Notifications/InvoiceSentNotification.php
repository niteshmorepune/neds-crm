<?php

namespace App\Notifications;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Portal notification counterpart to the App\Mail\InvoiceIssued mailable —
 * named distinctly to avoid an import collision with that Mailable.
 */
class InvoiceSentNotification extends Notification
{
    use Queueable;

    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $amount = Money::format($this->invoice->total);

        return [
            'type' => 'invoice_issued',
            'invoice_id' => $this->invoice->id,
            'message' => "Invoice {$this->invoice->invoice_number} issued — {$amount}",
            'url' => route('portal.invoices.show', $this->invoice->id),
        ];
    }
}
