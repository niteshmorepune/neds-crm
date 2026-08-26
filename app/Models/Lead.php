<?php

namespace App\Models;

use App\Enums\LeadBudgetBand;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadUrgency;
use App\Models\Concerns\LogsActivity;
use App\Observers\LeadObserver;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

#[ObservedBy(LeadObserver::class)]
class Lead extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'company',
        'phone',
        'alternate_phone',
        'email',
        'source',
        'service_id',
        'estimated_value',
        'owner_id',
        'status',
        'next_follow_up_at',
        'converted_customer_id',
        'converted_deal_id',
        'converted_at',
        'whatsapp_conversation_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'meta_leadgen_id',
    ];

    /**
     * AI score columns are written by the ScoreLead job, not user forms, and are
     * noise in the activity log — exclude them so an automated re-score isn't
     * recorded as a user "update".
     *
     * @var list<string>
     */
    protected array $activityExcept = [
        'ai_score', 'ai_score_reason', 'ai_scored_at',
        'ai_budget_band', 'ai_urgency', 'ai_service_fit',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'service_id' => 'integer',
            'estimated_value' => 'integer',
            'next_follow_up_at' => 'datetime',
            'converted_at' => 'datetime',
            'ai_score' => 'integer',
            'ai_scored_at' => 'datetime',
            'ai_budget_band' => LeadBudgetBand::class,
            'ai_urgency' => LeadUrgency::class,
            'owner_reminder_sent_at' => 'datetime',
            'manager_escalated_at' => 'datetime',
            'visibility_audit_invited_at' => 'datetime',
            'visibility_audit_invite_emailed_at' => 'datetime',
        ];
    }

    /** Hot leads get an immediate escalation notification instead of waiting for the digest. */
    public function isHot(): bool
    {
        return $this->ai_score !== null
            && $this->ai_score >= config('services.anthropic.hot_lead_threshold', 70);
    }

    public function isFollowUpOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->next_follow_up_at !== null
            && $this->next_follow_up_at->isPast();
    }

    public function isFollowUpDueToday(): bool
    {
        return $this->status->isOpen()
            && $this->next_follow_up_at !== null
            && $this->next_follow_up_at->isToday()
            && $this->next_follow_up_at->isFuture();
    }

    /**
     * Composite "what needs my attention" ranking for the Lead Generation
     * list's default Priority sort — computed in PHP (not raw SQL) so it
     * stays portable across the MySQL/SQLite split this app already has
     * between production and the test suite. AI score is the base signal;
     * an overdue follow-up outweighs everything (someone is waiting on a
     * promise), a follow-up due today matters less but still more than raw
     * score, and a still-New lead with no follow-up set yet accrues urgency
     * the longer it's sat untouched since creation (capped at 10 days so an
     * ancient, abandoned lead doesn't permanently dominate the top of the list).
     */
    public function priorityScore(): int
    {
        $score = $this->ai_score ?? 0;

        if (! $this->status->isOpen()) {
            return $score;
        }

        if ($this->isFollowUpOverdue()) {
            return $score + 100;
        }

        if ($this->isFollowUpDueToday()) {
            return $score + 50;
        }

        if ($this->next_follow_up_at === null && $this->status === LeadStatus::New) {
            $score += min($this->created_at->diffInDays(now()), 10) * 3;
        }

        return $score;
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function convertedDeal(): BelongsTo
    {
        return $this->belongsTo(Deal::class, 'converted_deal_id');
    }

    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest();
    }

    /**
     * The single most recent note — eager-loadable without an N+1, for the
     * Lead Generation list's "Latest Note" column.
     */
    public function latestNote(): MorphOne
    {
        return $this->morphOne(Note::class, 'notable')->latestOfMany();
    }

    /**
     * True when a human staff member has replied to this lead over WhatsApp
     * at or after $since — captured as a Note by WhatsappWebhookController::
     * noteBody(), the only place this exact "[Sent via WhatsApp by " prefix
     * is written. Excludes the AI after-hours assistant's own auto-replies
     * (same prefix, fixed "AI Assistant (auto-reply)" label) — a holding
     * message from the AI isn't a human taking over the conversation, so it
     * must not suppress a recovery nudge the way a real staff reply does.
     */
    public function hasStaffWhatsappReplySince(Carbon $since): bool
    {
        return $this->notes()
            ->where('created_at', '>=', $since)
            ->where('body', 'like', '[Sent via WhatsApp by %')
            ->where('body', 'not like', '[Sent via WhatsApp by AI Assistant (auto-reply)]%')
            ->exists();
    }

    /**
     * Deep link into wadesk.in's inbox, straight to this lead's own
     * conversation — optionally with $templateName pre-selected in the
     * template picker, pre-filled with this lead's own name/id using the
     * exact same {{1}}=name / buttonUrlParam=lead-id contract
     * SendVisibilityAuditRecoveryNudgeJob already sends automatically.
     * Staff still has to review and hit Send on wadesk's side (this never
     * sends anything itself) — it only saves the trip of finding the right
     * conversation and the right template by hand.
     *
     * Null when the lead was never staged in wadesk (SyncLeadToWadeskJob
     * hasn't run/succeeded yet — e.g. a lead with no phone) or wadesk
     * itself isn't configured — nothing to link to.
     */
    public function wadeskChatUrl(?string $templateName = null): ?string
    {
        if (blank($this->whatsapp_conversation_id)) {
            return null;
        }

        $baseUrl = rtrim((string) config('services.wadesk.base_url'), '/');
        if (! $baseUrl) {
            return null;
        }

        $query = ['conversation' => $this->whatsapp_conversation_id];

        if (filled($templateName)) {
            $query['template'] = $templateName;
            $query['var1'] = $this->name ?: 'there';
            $query['buttonParam'] = (string) $this->id;
        }

        return $baseUrl.'/inbox?'.http_build_query($query);
    }

    public function callLogs(): MorphMany
    {
        return $this->morphMany(CallLog::class, 'callable')->latest('called_at');
    }

    public function meetings(): MorphMany
    {
        return $this->morphMany(Meeting::class, 'meetable')->latest('occurred_at');
    }

    public function visibilityAuditFunnelEvents(): HasMany
    {
        return $this->hasMany(VisibilityAuditFunnelEvent::class);
    }

    public function visibilityAuditPurchases(): HasMany
    {
        return $this->hasMany(VisibilityAuditPurchase::class);
    }

    public function visibilityAuditTouches(): HasMany
    {
        return $this->hasMany(VisibilityAuditTouch::class);
    }

    /**
     * All roles see all leads. Access to the leads page is controlled by
     * the menu.access:lead-generation middleware; visibility within is unrestricted.
     * Keep in sync with LeadPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query;
    }

    /**
     * Finds an existing OPEN lead with this phone number, regardless of
     * source — used by ImportMetaLead and WhatsappWebhookController to stop
     * the same real-world enquiry (e.g. Meta's automatic "message us on
     * WhatsApp" follow-up after an Instant Form submit) from creating two
     * separate leads a few seconds apart. Restricted to open leads: a fresh
     * submission against an already-Converted/Lost lead reads as a genuine
     * new enquiry, not a duplicate of an old closed one.
     */
    public static function findOpenByPhone(string $rawPhone): ?self
    {
        $digits = Phone::digits($rawPhone);

        if ($digits === '') {
            return null;
        }

        return static::whereIn('status', LeadStatus::openValues())
            ->where(fn (Builder $q) => $q->where('phone', $digits)
                ->orWhere('phone', '+'.$digits)
                ->orWhere('phone', 'LIKE', '%'.Phone::last10($rawPhone)))
            ->latest()
            ->first();
    }
}
