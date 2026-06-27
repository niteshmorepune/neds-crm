<?php

namespace App\Notifications;

use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SmdostBriefApproved extends Notification
{
    use Queueable;

    public function __construct(
        public Invoice $invoice,
        public Customer $customer,
        public string $briefTitle,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'smdost_brief_approved',
            'invoice_id'   => $this->invoice->id,
            'customer_id'  => $this->customer->id,
            'customer'     => $this->customer->company_name,
            'brief_title'  => $this->briefTitle,
            'message'      => "All content approved — draft invoice ready for {$this->customer->company_name}",
            'url'          => route('invoices.show', $this->invoice->id),
        ];
    }
}
