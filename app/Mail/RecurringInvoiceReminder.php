<?php

namespace App\Mail;

use App\Models\RecurringInvoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RecurringInvoiceReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RecurringInvoice $recurring,
        public int $daysUntil,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Upcoming invoice reminder from '.config('company.name'),
        );
    }

    public function content(): Content
    {
        $subtotal = $this->recurring->items->sum(
            fn ($item) => (int) round($item->quantity * $item->rate)
        );

        return new Content(
            view: 'mail.recurring-invoice-reminder',
            with: [
                'recurring' => $this->recurring,
                'daysUntil' => $this->daysUntil,
                'subtotal' => Money::format($subtotal),
                'billingDate' => $this->recurring->next_run_on->format('d M Y'),
                'service' => $this->recurring->service?->name ?? 'your service',
                'companyName' => config('company.name'),
            ],
        );
    }
}
