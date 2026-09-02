<?php

namespace App\Models;

use App\Enums\DealStage;
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
        'lost_at',
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
            'lost_at' => 'datetime',
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

    /**
     * Stamp lost_at when status transitions to Lost (cleared if ever
     * reopened) -- mirrors Task::completed_at's exact saving() pattern, and
     * gives Lost the same "when did this actually close" timestamp
     * converted_at already gives Converted (that one is set explicitly in
     * ConvertLead instead, since conversion is a single funnel action; a
     * Lead can reach Lost from several places, so a model hook is the
     * single choke point that can't be missed). Powers the Score
     * Calibration report's time-to-close figure.
     */
    protected static function booted(): void
    {
        static::saving(function (Lead $lead) {
            if (! $lead->isDirty('status')) {
                return;
            }

            if ($lead->status === LeadStatus::Lost) {
                $lead->lost_at ??= now();
            } else {
                $lead->lost_at = null;
            }
        });
    }

    /** Hot leads get an immediate escalation notification instead of waiting for the digest. */
    public function isHot(): bool
    {
        return $this->ai_score !== null
            && $this->ai_score >= config('services.anthropic.hot_lead_threshold', 70);
    }

    /**
     * The Cold/Warm/Hot banding already shown on every AI-scored lead badge
     * (resources/views/components/lead-score.blade.php) — extracted here so
     * reports (Score Calibration, Loss Reason) bucket scores identically to
     * what a rep already sees on the lead itself, rather than inventing a
     * second banding. Hot's threshold follows the same configurable
     * hot_lead_threshold isHot() uses; Warm/Cold's 40 boundary mirrors the
     * badge component's own hardcoded value (not independently configurable).
     *
     * @return 'cold'|'warm'|'hot'|null null when there's no score to band.
     */
    public static function scoreBandFor(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= config('services.anthropic.hot_lead_threshold', 70) => 'hot',
            $score >= 40 => 'warm',
            default => 'cold',
        };
    }

    public static function scoreBandLabel(?string $band): string
    {
        return match ($band) {
            'hot' => 'Hot',
            'warm' => 'Warm',
            'cold' => 'Cold',
            default => 'No score data',
        };
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
     * between production and the test suite.
     *
     * Strict tiers, each one guaranteed to outrank every lead in the tier
     * below it regardless of AI score (the gaps between STALENESS_CAP,
     * DUE_TODAY_TIER and OVERDUE_TIER are all far larger than the maximum
     * possible score+nudge of any lower tier — 100 + STALENESS_CAP):
     *   1. Overdue follow-up — a broken promise to the client, always first.
     *   2. Due today — a commitment coming due, next.
     *   3. Everything else open — ranked by AI score first (a hotter lead
     *      is a better use of the team's time and must visually dominate),
     *      with a small "don't let it go cold" nudge for a New/Contacted
     *      lead with no follow-up scheduled, capped low enough that it can
     *      only break a near-tie, never flip a meaningfully hotter lead
     *      below a cooler one.
     *   4. Closed (Lost/Converted) — nothing left to follow up on, always
     *      last, regardless of how high its AI score was while still live.
     *
     * Within tiers 1-2, AI score still breaks ties among leads that share
     * the same urgency.
     *
     * Real production case, 2026-08-31: the previous formula's staleness
     * nudge (uncapped tier separation, up to +30 over 10 days) let six
     * three-week-old AI-45 leads (maxed out at +30 = 75) outrank two
     * genuinely hot AI-72 leads created hours earlier (barely any nudge
     * accrued yet, ~73-74) — the opposite of "hot leads on top." Shrunk the
     * nudge to STALENESS_CAP and moved the urgency tiers to fixed floors so
     * no combination of score+nudge can ever cross a tier boundary. Same
     * session also fixed a closed lead (Lost, AI 65) outranking open leads
     * — that fix (tier 4 above) is unchanged by this rebalance.
     */
    private const STALENESS_CAP = 8;

    private const STALENESS_WINDOW_DAYS = 20;

    private const DUE_TODAY_TIER = 500;

    private const OVERDUE_TIER = 1000;

    public function priorityScore(): int
    {
        $score = $this->ai_score ?? 0;

        if (! $this->status->isOpen()) {
            return $score - 1000;
        }

        if ($this->isFollowUpOverdue()) {
            return $score + self::OVERDUE_TIER;
        }

        if ($this->isFollowUpDueToday()) {
            return $score + self::DUE_TODAY_TIER;
        }

        if ($this->next_follow_up_at === null && in_array($this->status, [LeadStatus::New, LeadStatus::Contacted], true)) {
            $daysUntouched = min($this->created_at->diffInDays(now()), self::STALENESS_WINDOW_DAYS);
            $score += (int) round($daysUntouched * (self::STALENESS_CAP / self::STALENESS_WINDOW_DAYS));
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

    /**
     * "New" should reliably mean "genuinely never attempted" — promotes to
     * Contacted the moment ANY real outreach is recorded against a
     * still-New lead (any call outcome, or a note logged as a call via the
     * RecordNotes "This was a call" toggle — see
     * CallLogController::promoteLeadOnFirstOutreach() and
     * RecordNotes::addNote(), the two call sites), rather than depending on
     * every rep remembering to also flip the status field by hand. No-op
     * for a lead already past New. Goes through update() (not a raw query)
     * so LogsActivity still records it.
     */
    public function promoteFromNewOnOutreach(): void
    {
        if ($this->status === LeadStatus::New) {
            $this->update(['status' => LeadStatus::Contacted->value]);
        }
    }

    /**
     * A note created within this many seconds of the LEAD's own creation is
     * treated as part of the same intake transaction — the ingestion path
     * that captured this lead recording what the person submitted (a form
     * answer, a website enquiry message, a WhatsApp opening message), not
     * something that happened afterward. Second real bug in the same hour
     * (2026-09-03): fixed a body-prefix denylist for ImportMetaLead's
     * "Additional form answers:" note first, but the owner then hit the
     * identical false positive on a WEBSITE lead ("Ranu Jadhav") —
     * LeadCaptureController::store() auto-creates a note from the raw
     * `message` field with no prefix at all. A denylist keyed to specific
     * wording is whack-a-mole against every current AND future lead-capture
     * channel (Meta, website, WhatsApp, Visibility Audit purchase, and
     * whatever comes next); timing is the one signal that's actually true
     * of every intake path uniformly, without listing them out by name.
     * 10s tolerance covers real request-processing lag between Lead::create()
     * and the immediately-following notes()->create() call every one of
     * these jobs makes; a rep's own note, a later WhatsApp exchange, or a
     * Visibility Audit purchase on a lead that already existed will always
     * land well outside this window because real time has to pass first.
     */
    private const INTAKE_NOTE_WINDOW_SECONDS = 10;

    /**
     * Flags a lead still sitting at "New" that already has real activity
     * (a note created meaningfully after the lead itself, or a call log)
     * against it — the exact gap promoteFromNewOnOutreach() closes only
     * going forward: historical leads, and any future one where a rep
     * types a plain note without ticking "This was a call," still won't
     * auto-promote. Surfaced as a "Status may need updating" badge on the
     * Lead Generation list so a stale New count isn't silently
     * indistinguishable from a genuinely untouched one (2026-09-02, same
     * investigation as promoteFromNewOnOutreach()).
     *
     * Prefers already-loaded notes()/callLogs() relations (LeadController::
     * index()'s per-page eager load, or AiAssistant::suggestLeadStatusUpdate()'s
     * loadMissing()) so this stays cheap in a list; falls back to a real
     * query otherwise so it's still safe to call on a single unloaded model
     * (e.g. the lead show page).
     */
    public function hasStaleNewStatus(): bool
    {
        if ($this->status !== LeadStatus::New) {
            return false;
        }

        $callCount = $this->relationLoaded('callLogs')
            ? $this->callLogs->count()
            : ($this->call_logs_count ?? $this->callLogs()->count());

        if ($callCount > 0) {
            return true;
        }

        $notes = $this->relationLoaded('notes') ? $this->notes : $this->notes()->get(['id', 'created_at']);

        return $notes->contains(
            // Carbon 3 defaults diffInSeconds() to a SIGNED difference (the
            // note is normally later than the lead, giving a negative
            // number here) — absolute: true, not abs(), so a note somehow
            // backdated before the lead still counts as "outside the
            // window" rather than silently comparing as if it were recent.
            fn (Note $note) => $note->created_at->diffInSeconds($this->created_at, absolute: true) > self::INTAKE_NOTE_WINDOW_SECONDS
        );
    }

    /**
     * What "Converted" actually means for this lead right now, beyond the
     * bare status label. Lead.status = Converted only ever records that
     * ConvertLead::handle() ran — a Deal exists — it says nothing about
     * whether that Deal has since been won, lost, or is still being worked,
     * or whether a Won deal has actually been paid. Real incident
     * (2026-09-02): the owner couldn't tell from the Lead Generation list
     * which "Converted" leads were genuinely paying clients vs. still mid-
     * pipeline with a quotation sent but not yet accepted/paid.
     *
     * Deliberately queries fresh (Deal::withTrashed(), a direct Invoice
     * query) rather than relying on eager-loaded relations — two real gaps
     * caught by checking this against actual production data before
     * shipping: convertedDeal() excludes soft-deleted deals by default (2
     * of the 24 real Converted leads pointed at a since-deleted Deal), and
     * not every Invoice records which Deal it's for (Devraj Kanakappan/
     * ADTA Group's fully-paid ₹15,750 invoice has deal_id=NULL — logged via
     * the manual "Log Invoice" flow). Both would have silently under-
     * reported a real paying client as "unbilled" or shown nothing at all.
     *
     * Every non-null return is prefixed "Deal: " (except "Deal removed",
     * already unambiguous) — real confusion (2026-09-02): a plain "Lost"
     * caption under a green "Converted" badge read as a direct
     * contradiction ("Converted AND Lost?") even though it's correct — the
     * LEAD stayed Converted (a real Deal was created, a historical fact
     * that never changes), it's the resulting DEAL that was later lost.
     * The prefix makes clear which of the two the caption describes.
     *
     * Returns null when there's nothing more specific to say (not
     * Converted, or a Converted lead with no converted_deal_id at all).
     */
    public function conversionOutcomeLabel(): ?string
    {
        if ($this->status !== LeadStatus::Converted || $this->converted_deal_id === null) {
            return null;
        }

        $deal = Deal::withTrashed()->find($this->converted_deal_id);

        if ($deal === null) {
            return null;
        }

        if ($deal->trashed()) {
            return 'Deal removed';
        }

        if ($deal->stage !== DealStage::Won) {
            return 'Deal: '.$deal->stage->label();
        }

        // Match an invoice explicitly tied to this deal, OR one for the
        // same customer with no deal_id at all (see docblock above) — but
        // never one explicitly tied to a DIFFERENT deal, for a customer
        // with more than one.
        $invoices = Invoice::where('customer_id', $deal->customer_id)
            ->where(fn ($q) => $q->whereNull('deal_id')->orWhere('deal_id', $deal->id))
            ->get();

        if ($invoices->isEmpty()) {
            return 'Deal: Won (unbilled)';
        }

        $totalInvoiced = $invoices->sum('total');
        $totalPaid = $invoices->sum('amount_paid');

        return match (true) {
            $totalPaid >= $totalInvoiced => 'Deal: Won',
            $totalPaid > 0 => 'Deal: Won (partial payment)',
            default => 'Deal: Won (unpaid)',
        };
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
