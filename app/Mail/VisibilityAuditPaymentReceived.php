<?php

namespace App\Mail;

use App\Models\VisibilityAuditPurchase;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VisibilityAuditPaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisibilityAuditPurchase $purchase) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — '.config('company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.visibility-audit-payment-received',
            with: [
                'purchase' => $this->purchase,
                'amountPaid' => Money::format($this->purchase->amount_paise),
                'tierLabel' => $this->purchase->tier?->label() ?? 'Visibility Audit',
            ],
        );
    }
}
