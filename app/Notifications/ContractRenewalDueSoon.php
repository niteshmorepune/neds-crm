<?php

namespace App\Notifications;

use App\Models\RecurringInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContractRenewalDueSoon extends Notification
{
    use Queueable;

    public function __construct(public RecurringInvoice $recurringInvoice) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $client = $this->recurringInvoice->customer?->company_name ?? 'Unknown';
        $service = $this->recurringInvoice->service?->name ?? 'service';
        $endDate = $this->recurringInvoice->end_date?->format('d M Y') ?? '–';

        return [
            'type' => 'contract_renewal_due_soon',
            'recurring_invoice_id' => $this->recurringInvoice->id,
            'message' => "{$client}'s {$service} contract ends {$endDate} — renew or follow up before it lapses",
            'url' => route('recurring-invoices.show', $this->recurringInvoice->id),
        ];
    }
}
