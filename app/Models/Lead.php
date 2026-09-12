<?php

namespace App\Models;

use App\Enums\CallOutcome;
use App\Enums\DealStage;
use App\Enums\LeadBudgetBand;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\LeadUrgency;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Models\Concerns\LogsActivity;
use App\Observers\LeadObserver;
use App\Services\CallTimingMetrics;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
        'goal',
        'budget_range',
        'website_url',
        'gbp_url',
        'estimated_value',
        'recommendation_key',
        'recommendation_offer_key',
        'recommendation_token',
        'recommendation_generated_at',
        'recommendation_viewed_at',
        'offer_viewed_at',
        'offer_clicked_at',
        'owner_id',
        'telecaller_id',
        'status',
        'stall_reason',
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
     * recorded as a user "update". stall_reason is excluded for a different
     * reason: it IS a real, deliberate user action, but lastTouchedAt() (used
     * by ObjectionFollowUpDueSource/StallReasonMetrics to decide when a
     * stalling lead is stale enough to nudge about) reads this same
     * activities table — if tagging a lead as stalling counted as "just
     * touched," it would immediately reset its own staleness clock and the
     * nudge would never fire. Real bug caught by tests before shipping.
     *
     * @var list<string>
     */
    protected array $activityExcept = [
        'ai_score', 'ai_score_reason', 'ai_scored_at',
        'ai_budget_band', 'ai_urgency', 'ai_service_fit',
        'stall_reason',
        'recommendation_key', 'recommendation_offer_key', 'recommendation_token',
        'recommendation_generated_at', 'recommendation_viewed_at',
        'offer_viewed_at', 'offer_clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'source' => LeadSource::class,
            'status' => LeadStatus::class,
            'stall_reason' => StallReason::class,
            'goal' => LeadGoal::class,
            'budget_range' => LeadBudgetRange::class,
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
            'welcome_message_sent_at' => 'datetime',
            'last_checkin_sent_at' => 'datetime',
            'recommendation_generated_at' => 'datetime',
            'recommendation_viewed_at' => 'datetime',
            'offer_viewed_at' => 'datetime',
            'offer_clicked_at' => 'datetime',
            'recommendation_notified_at' => 'datetime',
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

    public function telecaller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'telecaller_id');
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

    /** How many combined real outreach attempts, per channel, and whether either one ever got a real response. */
    private const UNRESPONSIVE_ATTEMPT_THRESHOLD = 3;

    private const WHATSAPP_OUTBOUND_PREFIX = '[Sent via WhatsApp by';

    /**
     * Bodies of Lead-facing internal marker notes that are neither inbound
     * nor outbound WhatsApp traffic -- just "this automation ran" markers
     * (SendLeadWelcomeMessageJob / SendLeadCheckInJob's own confirmation
     * notes, both user_id=null with no WHATSAPP_OUTBOUND_PREFIX). Without
     * excluding these, outreachAttemptSummary() below would misclassify
     * them as an inbound reply the instant either job succeeds -- the same
     * known "user_id=null, no prefix" gap already documented on
     * whatsapp_inbound_reply, but unlike that rare intake-note case, this
     * one is guaranteed on every single welcomed/checked-in lead, so it
     * can't be left as an acceptable false negative here (it would make
     * Lead::isAwaitingWelcomeReply() permanently read "replied"). The two
     * "❌ ... failed to deliver" markers are WadeskMessageStatusController's
     * own downgrade notes (Meta's async delivery-failure webhook, e.g. the
     * "healthy ecosystem engagement" pacing throttle) -- same user_id=null,
     * no-prefix shape, same misclassification risk. The "⚠️ ... retry limit
     * reached" marker is RetryFailedLeadWelcomeMessages' own give-up note --
     * same shape again.
     */
    private const INTERNAL_MARKER_NOTE_PREFIXES = [
        '✨ Automated welcome message sent via WhatsApp',
        '✨ Re-engagement check-in sent via WhatsApp.',
        '❌ Welcome WhatsApp message failed to deliver',
        '❌ Re-engagement check-in failed to deliver',
        self::WELCOME_GIVE_UP_NOTE_PREFIX,
    ];

    /**
     * Extracted from LeadController::unresponsiveLeadIds() (2026-09-02) so
     * both the bulk list/strip-tile check and a single lead's own page (the
     * "next best action" advisor, AiAssistant::suggestLeadStatusUpdate())
     * share one source of truth instead of two copies drifting apart.
     * Prefers already-loaded notes()/callLogs() (same fallback convention as
     * hasStaleNewStatus()) so this stays cheap when eager-loaded.
     *
     * Known nuance, not fixed here (out of scope, pre-existing, and the
     * error direction is safe): `whatsapp_inbound_reply` can't currently
     * distinguish a genuine inbound WhatsApp reply from a Meta/website
     * intake note that happens to have no outbound-prefix either (both are
     * user_id=null, no prefix) — this only makes a lead look LESS
     * unresponsive than it is (a false negative), never more, so it doesn't
     * risk a wrongly-flagged lead the way hasStaleNewStatus()'s bug did.
     *
     * @return array{calls: int, whatsapp_outbound: int, connected_call: bool, whatsapp_inbound_reply: bool}
     */
    public function outreachAttemptSummary(): array
    {
        $callLogs = $this->relationLoaded('callLogs') ? $this->callLogs : $this->callLogs()->get(['id', 'callable_id', 'callable_type', 'outcome']);
        $notes = ($this->relationLoaded('notes') ? $this->notes : $this->notes()->get(['id', 'notable_id', 'notable_type', 'user_id', 'body']))
            ->reject(fn (Note $n) => collect(self::INTERNAL_MARKER_NOTE_PREFIXES)->contains(fn ($prefix) => str_starts_with($n->body, $prefix)));

        return [
            'calls' => $callLogs->count(),
            'whatsapp_outbound' => $notes->filter(fn (Note $n) => $n->user_id === null && str_starts_with($n->body, self::WHATSAPP_OUTBOUND_PREFIX))->count(),
            'connected_call' => $callLogs->contains(fn (CallLog $c) => $c->outcome === CallOutcome::Connected),
            'whatsapp_inbound_reply' => $notes->contains(fn (Note $n) => $n->user_id === null && ! str_starts_with($n->body, self::WHATSAPP_OUTBOUND_PREFIX)),
        ];
    }

    /**
     * UNRESPONSIVE_ATTEMPT_THRESHOLD+ combined outreach attempts (calls of
     * any outcome, plus outbound WhatsApp sends), with no successful
     * response on either channel, and still open. See
     * LeadController::unresponsiveLeadIds()'s own docblock (where this
     * logic originated, 2026-09-02) for the full reasoning.
     */
    public function isUnresponsive(): bool
    {
        if (! $this->status->isOpen()) {
            return false;
        }

        $summary = $this->outreachAttemptSummary();

        if ($summary['connected_call'] || $summary['whatsapp_inbound_reply']) {
            return false;
        }

        return ($summary['calls'] + $summary['whatsapp_outbound']) >= self::UNRESPONSIVE_ATTEMPT_THRESHOLD;
    }

    /**
     * Same "don't depend on a human noticing" wait used by
     * SendLeadWelcomeFollowUps' own one-shot automatic check-in, and by the
     * Lead Generation "welcome sent, no reply" badge/count so neither ever
     * disagrees with the other about what counts as "gone quiet." Owner-
     * approved 2026-09-09 (asked for 4-6 hours; picked the midpoint —
     * same-day cadence without being pushy).
     */
    public const WELCOME_FOLLOWUP_WAIT_HOURS = 6;

    /**
     * True once the automatic Meta Ads welcome message (SendLeadWelcomeMessageJob)
     * has gone out and nobody -- the lead, staff, or the after-hours AI --
     * has said anything back over WhatsApp since. Reuses
     * outreachAttemptSummary()'s existing note-scanning (rather than a
     * second copy) so this can never drift from isUnresponsive()'s own
     * reply detection. No time threshold here -- see
     * isOverdueForWelcomeReply() below for the narrowed, "worth surfacing"
     * version.
     */
    public function isAwaitingWelcomeReply(): bool
    {
        if ($this->welcome_message_sent_at === null || ! $this->status->isOpen()) {
            return false;
        }

        $summary = $this->outreachAttemptSummary();

        return ! $summary['whatsapp_inbound_reply'] && $summary['whatsapp_outbound'] === 0;
    }

    /**
     * isAwaitingWelcomeReply(), narrowed to "it's been long enough that
     * this is actually worth surfacing" -- drives the Lead Generation
     * per-row badge. Deliberately does NOT check last_checkin_sent_at (the
     * one-shot automatic nudge already having fired doesn't make a lead
     * any less worth a phone call) -- that guard belongs to
     * SendLeadWelcomeFollowUps alone, so a lead can't be auto-nudged twice.
     */
    public function isOverdueForWelcomeReply(): bool
    {
        return $this->isAwaitingWelcomeReply()
            && $this->welcome_message_sent_at->lte(now()->subHours(self::WELCOME_FOLLOWUP_WAIT_HOURS));
    }

    /**
     * Real incident, 2026-09-10: Meta's own "healthy ecosystem engagement"
     * pacing throttle (error 131049) rejected several lead_welcome sends —
     * WadeskMessageStatusController::handle() correctly detects this and
     * resets welcome_message_sent_at/welcome_message_wadesk_id to null (see
     * that controller's docblock), but nothing then re-attempts the send —
     * the lead just sits with no automation scheduled to ever reach it
     * again. RetryFailedLeadWelcomeMessages closes that gap by re-dispatching
     * SendLeadWelcomeMessageJob (whose own idempotency guard already permits
     * this, since it only checks welcome_message_sent_at !== null) for a
     * lead that genuinely failed before, with backoff + a hard attempt cap
     * so a persistently-throttled recipient doesn't get hammered forever.
     */
    public const WELCOME_RETRY_WAIT_HOURS = 2;

    public const WELCOME_RETRY_MAX_ATTEMPTS = 3;

    private const WELCOME_FAILURE_NOTE_PREFIX = '❌ Welcome WhatsApp message failed to deliver';

    private const WELCOME_GIVE_UP_NOTE_PREFIX = '⚠️ Automated welcome message retry limit reached';

    /** Every past delivery-failure note for this lead's welcome message, oldest first. */
    private function welcomeMessageFailureNotes(): Collection
    {
        $notes = $this->relationLoaded('notes') ? $this->notes : $this->notes()->get(['id', 'notable_id', 'notable_type', 'body', 'created_at']);

        return $notes
            ->filter(fn (Note $n) => str_starts_with($n->body, self::WELCOME_FAILURE_NOTE_PREFIX))
            ->sortBy('created_at')
            ->values();
    }

    /**
     * True once WELCOME_RETRY_MAX_ATTEMPTS failed sends have piled up for
     * this lead — RetryFailedLeadWelcomeMessages stops retrying at that
     * point, deliberately leaving the lead to a staff member's own manual
     * "Send WhatsApp check-in" click rather than retrying a throttle
     * indefinitely.
     */
    public function hasGivenUpOnWelcomeMessage(): bool
    {
        return $this->welcomeMessageFailureNotes()->count() >= self::WELCOME_RETRY_MAX_ATTEMPTS;
    }

    /**
     * True exactly once, the first run after hasGivenUpOnWelcomeMessage()
     * starts returning true — guards RetryFailedLeadWelcomeMessages' own
     * give-up note so it posts once, not on every future 30-min run (nothing
     * about a gave-up lead ever changes again on its own, so the failure
     * count staying >= the cap forever would otherwise re-fire this note
     * indefinitely).
     */
    public function needsWelcomeRetryGiveUpNote(): bool
    {
        if (! $this->hasGivenUpOnWelcomeMessage()) {
            return false;
        }

        $notes = $this->relationLoaded('notes') ? $this->notes : $this->notes()->get(['id', 'notable_id', 'notable_type', 'body']);

        return ! $notes->contains(fn (Note $n) => str_starts_with($n->body, self::WELCOME_GIVE_UP_NOTE_PREFIX));
    }

    /**
     * A previously-failed welcome message is worth retrying once: it has
     * never actually reached the lead (welcome_message_sent_at null), it has
     * genuinely failed before (at least one failure note — distinct from a
     * brand-new lead that's simply never been attempted yet, which is
     * LeadObserver's job, not this one), it hasn't piled up
     * WELCOME_RETRY_MAX_ATTEMPTS failures already, and enough time has
     * passed since the last failure to give Meta's pacing throttle a chance
     * to ease off rather than immediately re-hammering it.
     */
    public function isEligibleForWelcomeMessageRetry(): bool
    {
        if ($this->welcome_message_sent_at !== null || $this->last_checkin_sent_at !== null) {
            return false;
        }

        $failures = $this->welcomeMessageFailureNotes();

        if ($failures->isEmpty() || $this->hasGivenUpOnWelcomeMessage()) {
            return false;
        }

        return $failures->last()->created_at->lte(now()->subHours(self::WELCOME_RETRY_WAIT_HOURS));
    }

    /**
     * The concrete next move for an unresponsive lead — owner's own framing
     * (2026-09-03): "what can be the next best action... to follow up the
     * lead." Deterministic, not AI (this is a cheap, always-available tier;
     * AI-drafted follow-up via the existing Draft Follow-up button is the
     * next tier once a channel switch alone isn't enough). Null when the
     * lead isn't actually unresponsive — callers don't need to check both.
     */
    public function suggestedNextAction(CallTimingMetrics $callTiming): ?string
    {
        if (! $this->isUnresponsive()) {
            return null;
        }

        $summary = $this->outreachAttemptSummary();
        $triedCalls = $summary['calls'] > 0;
        $triedWhatsapp = $summary['whatsapp_outbound'] > 0;
        $timingHint = $callTiming->summaryLine();

        if ($triedCalls && ! $triedWhatsapp) {
            return "You've called {$summary['calls']} time(s) with no answer, but never messaged on WhatsApp — try that channel next.";
        }

        if ($triedWhatsapp && ! $triedCalls) {
            return "You've messaged on WhatsApp {$summary['whatsapp_outbound']} time(s) with no reply, but never called — try calling instead"
                .($timingHint ? " ({$timingHint})" : '').'.';
        }

        return "Both calls ({$summary['calls']}) and WhatsApp ({$summary['whatsapp_outbound']}) have been tried with no response."
            .($timingHint ? " If you try calling again, {$timingHint}." : '')
            .' Otherwise, consider updating the status below or sending one more message via ✨ Draft follow-up.';
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

    /**
     * Most recent real activity on this lead — a call, a note, or any
     * logged edit (status change, reassignment, etc.) — or its own
     * creation time if genuinely never touched since. Mirrors
     * Deal::lastTouchedAt() exactly, plus callLogs (Deals have no direct
     * call log of their own; a Lead's calls are its primary working
     * mechanism, so leaving them out here would understate real activity).
     * Single source of truth for "when did anyone last work this lead" —
     * used by ObjectionFollowUpDueSource/StallReasonMetrics and the
     * stall-follow-up drafting job so they can never silently disagree on
     * what counts as stale.
     */
    public function lastTouchedAt(): Carbon
    {
        return collect([
            $this->notes()->max('created_at'),
            $this->activities()->max('created_at'),
            $this->callLogs()->max('called_at'),
            $this->created_at,
        ])->filter()->map(fn ($value) => Carbon::parse($value))->max();
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

    public function offerPurchases(): HasMany
    {
        return $this->hasMany(OfferPurchase::class);
    }

    public function offerFunnelEvents(): HasMany
    {
        return $this->hasMany(OfferFunnelEvent::class);
    }

    public function firstName(): ?string
    {
        if (blank($this->name)) {
            return null;
        }

        return trim(explode(' ', trim($this->name))[0]) ?: null;
    }

    /**
     * The personalized recommendation page's public, unguessable URL —
     * generates recommendation_token lazily on first call if not already
     * set, same non-expiring lazy-token pattern as Quotation::publicViewUrl()
     * / VisibilityAuditPurchase::reportUrl(). Deliberately never the bare
     * lead id — this page renders real goal/budget/name back on screen, so
     * an incrementing id would let one lead's URL reveal another's.
     */
    public function recommendationUrl(): string
    {
        if ($this->recommendation_token === null) {
            $this->forceFill(['recommendation_token' => (string) Str::uuid()])->save();
        }

        return route('offers.recommendation', $this->recommendation_token);
    }

    /**
     * Admin/Manager see every lead. Sales sees their own (or unowned) leads
     * only — real incident 2026-09-03: Kiran and Mohit (both Sales) could
     * see each other's leads under the old "everyone sees everything" rule.
     * Telecaller sees only leads assigned to them via telecaller_id (their
     * own separate assignment, independent of owner_id) — this reverses the
     * 2026-07-26 "shared calling queue, no ownership" decision, per the
     * owner's follow-up request the same day as the Sales fix above. A
     * multi-role user's access only ever widens (matches
     * Customer::scopeVisibleTo's own precedent) — any other role reaching
     * this page (e.g. via a per-user Menu Controller override) keeps full
     * access, unaffected by this change. Keep in sync with LeadPolicy::view.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasRole(UserRole::Admin, UserRole::Manager)) {
            return $query;
        }

        if (! $user->hasRole(UserRole::Sales, UserRole::Telecaller)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            if ($user->hasRole(UserRole::Sales)) {
                $q->orWhere(fn (Builder $q2) => $q2->where('owner_id', $user->id)->orWhereNull('owner_id'));
            }

            if ($user->hasRole(UserRole::Telecaller)) {
                $q->orWhere('telecaller_id', $user->id);
            }
        });
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
