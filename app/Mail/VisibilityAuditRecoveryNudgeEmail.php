<?php

namespace App\Mail;

use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sibling of SendVisibilityAuditRecoveryNudgeJob's WhatsApp
 * templates — one Mailable, copy/CTA varies by $stage the same way the
 * WhatsApp job picks between two templates. The checkout-stage link
 * hardcodes tier=gbp, matching the existing WhatsApp template's own base
 * URL (this whole cohort is the GMB-tagged Meta Ads funnel — there is no
 * other tier in play here).
 */
class VisibilityAuditRecoveryNudgeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Lead $lead,
        public VisibilityAuditFunnelEventType $stage,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->stage === VisibilityAuditFunnelEventType::PaymentViewed
            ? 'Still interested in your free Google Business Profile Audit?'
            : 'Your free Google Business Profile Audit is waiting';

        // Reply-To (not From — see config/company.php for why) plus a CC to
        // the Lead's assigned owner, so the rep sees every customer-facing
        // email that goes out on a lead they own.
        return new Envelope(
            subject: $subject.' — '.config('company.name'),
            replyTo: [config('company.reply_to_email')],
            cc: array_filter([$this->lead->owner?->email]),
        );
    }

    public function content(): Content
    {
        $offerUrl = $this->stage === VisibilityAuditFunnelEventType::PaymentViewed
            ? route('offers.visibility-audit.checkout', ['tier' => 'gbp', 'lead' => $this->lead->id])
            : route('offers.visibility-audit.enter', ['lead' => $this->lead->id]);

        return new Content(
            view: 'mail.visibility-audit-recovery-nudge',
            with: [
                'lead' => $this->lead,
                'stage' => $this->stage,
                'offerUrl' => $offerUrl,
            ],
        );
    }
}
