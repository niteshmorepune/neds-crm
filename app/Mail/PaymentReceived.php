<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public Payment $payment,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment received — '.config('company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-received',
            with: [
                'invoice' => $this->invoice,
                'payment' => $this->payment,
                'amountPaid' => Money::format($this->payment->amount),
                'balance' => Money::format($this->invoice->balance()),
            ],
        );
    }
}
