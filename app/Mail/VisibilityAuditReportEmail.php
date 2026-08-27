<?php

namespace App\Mail;

use App\Models\VisibilityAuditPurchase;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment as MailAttachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Step 4 of the post-payment conversion pipeline — the real report file
 * attached directly (unlike the WhatsApp side, which links to
 * VisibilityAuditPurchase::reportUrl() instead, since wadesk.in's template
 * contract has no document-header support; email attachments are trivial
 * by comparison, so no reason not to send the real file here).
 */
class VisibilityAuditReportEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public VisibilityAuditPurchase $purchase) {}

    public function envelope(): Envelope
    {
        $ownerEmail = $this->purchase->lead?->owner?->email;

        return new Envelope(
            subject: 'Your Visibility Audit report — '.config('company.name'),
            replyTo: [config('company.reply_to_email')],
            cc: array_filter([$ownerEmail]),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.visibility-audit-report',
            with: [
                'purchase' => $this->purchase,
                'tierLabel' => $this->purchase->tier?->label() ?? 'Visibility Audit',
            ],
        );
    }

    /**
     * @return array<int, MailAttachment>
     */
    public function attachments(): array
    {
        $attachment = $this->purchase->reportAttachment();

        if ($attachment === null) {
            return [];
        }

        return [
            MailAttachment::fromStorageDisk($attachment->disk, $attachment->path)
                ->as($attachment->original_name)
                ->withMime($attachment->mime_type ?? 'application/octet-stream'),
        ];
    }
}
