<?php

namespace App\Models;

use App\Enums\VisibilityAuditTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A completed Razorpay payment against the Visibility Audit offer landing
 * page (/offers/visibility-audit). Recorded purely for idempotency
 * (razorpay_payment_id is unique — same guard pattern as Payment's
 * gateway_payment_id) and reconciliation; the real follow-up signal lives
 * on the matched Lead's note, not this row.
 */
class VisibilityAuditPurchase extends Model
{
    protected $fillable = [
        'tier',
        'amount_paise',
        'razorpay_payment_id',
        'razorpay_order_id',
        'payer_name',
        'payer_phone',
        'payer_email',
        'gbp_url',
        'website_url',
        'lead_id',
        'in_progress_notified_at',
        'in_progress_notified_email_at',
        'audit_ready_at',
        'audit_ready_by',
        'report_token',
        'report_sent_at',
        'report_sent_by',
    ];

    protected function casts(): array
    {
        return [
            'tier' => VisibilityAuditTier::class,
            'amount_paise' => 'integer',
            'in_progress_notified_at' => 'datetime',
            'in_progress_notified_email_at' => 'datetime',
            'audit_ready_at' => 'datetime',
            'report_sent_at' => 'datetime',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function readyByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audit_ready_by');
    }

    public function reportSentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_sent_by');
    }

    /**
     * The uploaded report file(s) — reuses the existing polymorphic
     * Attachment model (zero schema change to it), same pattern as
     * Ticket::attachments()/ContentPiece::attachments(). In practice one
     * per purchase; latest() so a re-upload naturally becomes "the" report
     * without needing to delete the old one first.
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function reportAttachment(): ?Attachment
    {
        return $this->attachments()->first();
    }

    /**
     * The public, permanent report-view link — generates report_token on
     * first call if not already set (a UUID, same shape as ContentPiece's
     * own upload_token) rather than requiring a separate "generate token"
     * step before the report can ever be sent. Deliberately non-expiring,
     * unlike ContentPiece's 7-day upload window — this is a lasting
     * reference the client may revisit, not a one-time action window.
     */
    public function reportUrl(): string
    {
        if ($this->report_token === null) {
            $this->forceFill(['report_token' => (string) Str::uuid()])->save();
        }

        return route('offers.visibility-audit.report', $this->report_token);
    }

    /**
     * Step 3 of the post-payment conversion pipeline: a real Meeting exists
     * for the matched Lead whose scheduled time has already passed — the
     * same "no separate held/not-held flag, derive it" convention as every
     * other computed signal in this pipeline (e.g. VisibilityAuditFunnelMetrics
     * itself). A future-dated Meeting (scheduled but not yet happened)
     * deliberately does NOT count.
     */
    public function hasHeldGmeet(): bool
    {
        return $this->lead !== null && $this->lead->meetings()->where('occurred_at', '<=', Carbon::now())->exists();
    }
}
