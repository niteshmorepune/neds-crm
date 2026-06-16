<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssued extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Invoice {$this->invoice->invoice_number} from ".config('company.name'),
        );
    }

    public function content(): Content
    {
        $this->invoice->loadMissing('milestones');

        return new Content(
            view: 'mail.invoice-issued',
            with: [
                'invoice' => $this->invoice,
                'total' => Money::format($this->invoice->total),
            ],
        );
    }
}
