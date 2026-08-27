<?php

namespace App\Models;

use App\Enums\VisibilityAuditTier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

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
    ];

    protected function casts(): array
    {
        return [
            'tier' => VisibilityAuditTier::class,
            'amount_paise' => 'integer',
            'in_progress_notified_at' => 'datetime',
            'in_progress_notified_email_at' => 'datetime',
            'audit_ready_at' => 'datetime',
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
