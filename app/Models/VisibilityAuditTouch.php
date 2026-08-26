<?php

namespace App\Models;

use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One outbound touch in the Visibility Audit funnel — an AI-automated
 * WhatsApp send attempt (first invite / recovery nudge / payment
 * confirmation) or a Sales/Telecaller manual call against a lead already in
 * the VA cohort (auto-logged from CallLogController::store(), no separate
 * staff-facing UI — see VisibilityAuditFunnelMetrics::isVisibilityAuditCohort()).
 * Append-only; `success = false` rows capture a send attempt that failed
 * (previously only a Log::warning, unqueryable) rather than being silently
 * dropped. `lead_id` is required — this table only ever logs a touch
 * attributable to a specific Lead, unlike VisibilityAuditFunnelEvent, which
 * also records anonymous page-view hits.
 */
class VisibilityAuditTouch extends Model
{
    protected $fillable = [
        'lead_id',
        'touch_type',
        'channel',
        'actor_user_id',
        'occurred_at',
        'success',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'touch_type' => VisibilityAuditTouchType::class,
            'channel' => VisibilityAuditTouchChannel::class,
            'occurred_at' => 'datetime',
            'success' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * Logs one AI-automated send attempt (success or failure) — the shared
     * write path for every AI-automated Visibility Audit job, WhatsApp
     * (SendVisibilityAuditFirstInviteJob/SendVisibilityAuditRecoveryNudgeJob/
     * SendVisibilityAuditPaymentConfirmationJob) and email
     * (their *EmailJob siblings), so none of them redefine channel/actor
     * defaults themselves. $channel defaults to AiWhatsapp to keep every
     * existing WhatsApp call site unchanged.
     */
    public static function logSend(int $leadId, VisibilityAuditTouchType $type, bool $success, ?array $meta = null, VisibilityAuditTouchChannel $channel = VisibilityAuditTouchChannel::AiWhatsapp): void
    {
        static::create([
            'lead_id' => $leadId,
            'touch_type' => $type,
            'channel' => $channel,
            'actor_user_id' => null,
            'occurred_at' => now(),
            'success' => $success,
            'meta' => $meta,
        ]);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
