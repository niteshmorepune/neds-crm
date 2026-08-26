<?php

namespace App\Mail;

use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sibling of SendVisibilityAuditInProgressJob's WhatsApp template —
 * step 2 of the post-payment conversion pipeline.
 */
class VisibilityAuditInProgressEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisibilityAuditPurchase $purchase) {}

    public function envelope(): Envelope
    {
        // Reply-To (not From — see config/company.php for why) plus a CC to
        // whoever owns the matched Lead, same convention as
        // VisibilityAuditPaymentReceived.
        $ownerEmail = $this->purchase->lead?->owner?->email;

        return new Envelope(
            subject: 'Your Visibility Audit is underway — '.config('company.name'),
            replyTo: [config('company.reply_to_email')],
            cc: array_filter([$ownerEmail]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.visibility-audit-in-progress',
            with: [
                'purchase' => $this->purchase,
                'tierLabel' => $this->purchase->tier?->label() ?? 'Visibility Audit',
            ],
        );
    }
}
