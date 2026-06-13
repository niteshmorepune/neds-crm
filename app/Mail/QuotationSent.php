<?php

namespace App\Mail;

use App\Models\Quotation;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotationSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quotation $quotation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Quotation {$this->quotation->number} from ".config('company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.quotation-sent',
            with: [
                'quotation' => $this->quotation,
                'total' => Money::format($this->quotation->total),
            ],
        );
    }
}
