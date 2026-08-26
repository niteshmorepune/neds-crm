<?php

namespace App\Mail;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sibling of SendVisibilityAuditFirstInviteJob's WhatsApp template —
 * the first time a Meta Ads lead (tagged GMB) is actually shown the
 * Visibility Audit offer, since Meta's own lead form never sends them
 * anywhere. Same tracked entry link as the WhatsApp button
 * (offers.visibility-audit.enter?lead=...), so a click via either channel
 * attributes back to this Lead identically.
 */
class VisibilityAuditFirstInviteEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your free Google Business Profile Audit — '.config('company.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.visibility-audit-first-invite',
            with: [
                'lead' => $this->lead,
                'offerUrl' => route('offers.visibility-audit.enter', ['lead' => $this->lead->id]),
            ],
        );
    }
}
