<?php

namespace App\Services;

use App\Enums\DealStage;
use App\Enums\LeadGoal;
use App\Enums\LeadStatus;
use App\Enums\QuotationStatus;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Meeting;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lead Generation list's "Next Action" column (2026-09-17 plan, see the
 * [[lead-next-action-column-plan]] memory) — what the CRM thinks a rep
 * should do next for ONE lead, instead of the "Latest Note" column's bare
 * last-note text (often unhelpful at a glance: a raw Meta-import backfill
 * note, a "call not answered" line, or a `[location]` placeholder).
 *
 * This class stays a pure reader of already-computed fields — it never
 * calls AI itself, so it's still safe to run on every row of every page
 * load with no per-row latency/cost. Originally (2026-09-17) that meant a
 * fully deterministic rule chain. 2026-09-18 (later): the owner reviewed
 * three real leads where that deterministic chain — each rule looking at
 * exactly one isolated signal — produced a stale or generic hint despite
 * the lead's own page telling a much more specific story once read end to
 * end (e.g. a later failed call attempt should supersede an earlier logged
 * commitment, which no single-signal rule can know to do). aiHint() now
 * checks App\Jobs\AnalyzeLeadNextAction's cached, full-context output
 * FIRST — every rule below this point is now the FALLBACK for a lead that
 * job hasn't analyzed yet (brand new, no notes/calls, or AI disabled), not
 * the primary source. Kept exactly as before for that fallback role.
 *
 * Priority-ordered rules, same "first non-null wins" pattern
 * NextActionEngine::SOURCES already uses for a user's whole day, scoped
 * down to one record. Each rule reuses an existing signal rather than
 * re-deriving it — see the memory plan's own signal table.
 */
class LeadNextActionAdvisor
{
    /** Mirrors ObjectionFollowUpDueSource::STALE_DAYS exactly — same "gone quiet" threshold, applied to one lead instead of a team-wide scan. */
    private const STALL_STALE_DAYS = 3;

    /** A Meeting on this lead within this many hours counts as "soon" for this column (looser than MeetingStartingSoonSource's tight 10-minute "join now" window — this is a daily list, not a live banner). */
    private const MEETING_SOON_HOURS = 24;

    public function __construct(private readonly LeadCallTimingAdvisor $callTiming) {}

    /**
     * @param  Collection<int, array{hour: int, total: int, connected: int, rate: float}>|null  $bestHours  Pass a pre-computed CallTimingMetrics::bestHours() when calling this in a loop (list pages) so the underlying query only runs once per request — same convention as LeadCallTimingAdvisor::recommendationFor().
     * @return array{label: string, detail: ?string}
     */
    public function hintFor(Lead $lead, ?Collection $bestHours = null): array
    {
        return $this->aiHint($lead)
            ?? $this->stallOverdue($lead)
            ?? $this->callLogFollowUpWithInstruction($lead)
            ?? $this->followUpDue($lead)
            ?? $this->meetingSoon($lead)
            ?? $this->sendQuotation($lead)
            ?? $this->scheduleMeeting($lead)
            ?? $this->needsLink($lead)
            ?? $this->notSure($lead)
            ?? $this->welcomeNoReply($lead)
            ?? $this->neverCalled($lead, $bestHours)
            ?? $this->fallbackNote($lead);
    }

    /**
     * App\Jobs\AnalyzeLeadNextAction's cached output — see the class
     * docblock. Not gated on $lead->status->isOpen() the way most rules
     * below are: that job itself already skips Lost leads (never writes a
     * hint for one), and deliberately does NOT skip Converted (a Converted
     * lead's "send the quotation"/"schedule the meeting" is exactly the
     * kind of next action it should surface) — trusting its own gate here
     * rather than re-deriving a narrower one.
     *
     * @return ?array{label: string, detail: ?string}
     */
    private function aiHint(Lead $lead): ?array
    {
        if ($lead->ai_next_action_hint === null) {
            return null;
        }

        return [
            'label' => '✨ '.$lead->ai_next_action_hint,
            'detail' => 'AI-analyzed from the lead\'s full history '.$lead->ai_next_action_generated_at?->diffForHumans().'.',
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function stallOverdue(Lead $lead): ?array
    {
        if ($lead->stall_reason === null || ! $lead->status->isOpen()) {
            return null;
        }

        $lastTouch = $lead->lastTouchedAt();

        if ($lastTouch->gt(now()->subDays(self::STALL_STALE_DAYS))) {
            return null;
        }

        return [
            'label' => "🎯 Stalling: {$lead->stall_reason->label()}",
            'detail' => "No contact since {$lastTouch->diffForHumans()} — follow up naming the objection.",
        ];
    }

    /**
     * A due/overdue CallLog follow-up whose next_action text is filled in —
     * either typed by the rep themselves on the Log a Call form, or written
     * by DetectCallFollowUpCommitment (live since 2026-08-31) after it read
     * the call's own notes and found a real commitment ("Send proposal",
     * "Confirm office visit time"). Checked before the generic Lead-level
     * followUpDue() below, since this is always the more specific of the
     * two whenever both happen to apply. Mirrors CallLog::scopeFollowUpDue()'s
     * own semantics (open Lead only) without needing a live query — this
     * runs against the already-loaded, per-page callLogs collection.
     *
     * @return ?array{label: string, detail: ?string}
     */
    private function callLogFollowUpWithInstruction(Lead $lead): ?array
    {
        if (! $lead->status->isOpen()) {
            return null;
        }

        $callLogs = $lead->relationLoaded('callLogs') ? $lead->callLogs : $lead->callLogs()->get();

        $due = $callLogs
            ->filter(fn (CallLog $call) => $call->follow_up_at !== null && $call->follow_up_at->isPast() && filled($call->next_action))
            ->sortBy('follow_up_at')
            ->first();

        if ($due === null) {
            return null;
        }

        return [
            'label' => "📞 {$due->next_action}",
            'detail' => 'Noted at the last call, due '.$due->follow_up_at->diffForHumans().'.',
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function followUpDue(Lead $lead): ?array
    {
        if ($lead->isFollowUpOverdue()) {
            return [
                // ai_detected_next_action is written by
                // DetectLeadNoteFollowUpCommitment whenever it set this same
                // next_follow_up_at from a plain note's commitment — shown
                // in place of the generic label whenever present.
                'label' => $lead->ai_detected_next_action !== null
                    ? "🔴 {$lead->ai_detected_next_action}"
                    : '🔴 Follow up now — overdue',
                'detail' => 'Promised follow-up was due '.$lead->next_follow_up_at->diffForHumans().'.',
            ];
        }

        if ($lead->isFollowUpDueToday()) {
            return [
                'label' => $lead->ai_detected_next_action !== null
                    ? "🟡 {$lead->ai_detected_next_action}"
                    : '🟡 Follow up today',
                'detail' => 'Due '.$lead->next_follow_up_at->timezone(config('app.display_timezone'))->format('g:i A').' today.',
            ];
        }

        return null;
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function meetingSoon(Lead $lead): ?array
    {
        $meetings = $lead->relationLoaded('meetings') ? $lead->meetings : $lead->meetings()->get();

        $upcoming = $meetings
            ->filter(fn (Meeting $meeting) => $meeting->occurred_at?->isFuture() && $meeting->occurred_at->lte(now()->addHours(self::MEETING_SOON_HOURS)))
            ->sortBy('occurred_at')
            ->first();

        if ($upcoming === null) {
            return null;
        }

        return [
            'label' => '📅 Meeting: '.$upcoming->occurred_at->diffForHumans(),
            'detail' => $upcoming->title,
        ];
    }

    /**
     * A converted lead's own Deal, still open, with no Quotation that's ever
     * reached Sent/Accepted — the concrete "what to send next" the owner
     * asked for by name. Lazy-loads convertedDeal/quotations rather than an
     * eager load in the controller: only a fraction of a page's 15 rows are
     * ever Converted, so a per-row query here stays cheap at this scale
     * (same precedent as every other lazy-fallback rule in this class).
     *
     * @return ?array{label: string, detail: ?string}
     */
    private function sendQuotation(Lead $lead): ?array
    {
        if ($lead->status !== LeadStatus::Converted || $lead->converted_deal_id === null) {
            return null;
        }

        $deal = $lead->convertedDeal;

        if ($deal === null || in_array($deal->stage, [DealStage::Won, DealStage::Lost], true)) {
            return null;
        }

        $alreadySent = $deal->quotations()->where('status', '!=', QuotationStatus::Draft->value)->exists();

        if ($alreadySent) {
            return null;
        }

        return [
            'label' => '📄 Send the quotation',
            'detail' => "Deal \"{$deal->title}\" ({$deal->stage->label()}) has no quotation sent yet.",
        ];
    }

    /**
     * A converted lead's Deal at Proposal/Negotiation — the stage where a
     * real conversation genuinely helps move things forward — with no
     * Meeting ever logged against the lead. Deals have no meetings relation
     * of their own in this app (see Lead::meetings()); a Deal's meetings are
     * tracked against its originating Lead.
     *
     * @return ?array{label: string, detail: ?string}
     */
    private function scheduleMeeting(Lead $lead): ?array
    {
        if ($lead->status !== LeadStatus::Converted || $lead->converted_deal_id === null) {
            return null;
        }

        $deal = $lead->convertedDeal;

        if ($deal === null || ! in_array($deal->stage, [DealStage::Proposal, DealStage::Negotiation], true)) {
            return null;
        }

        $meetings = $lead->relationLoaded('meetings') ? $lead->meetings : $lead->meetings()->get();

        if ($meetings->isNotEmpty()) {
            return null;
        }

        return [
            'label' => '📅 Schedule a meeting',
            'detail' => "Deal \"{$deal->title}\" is at {$deal->stage->label()} with no meeting held yet.",
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function needsLink(Lead $lead): ?array
    {
        if (! $lead->goal?->needsWebsiteOrGbp() || $lead->website_url || $lead->gbp_url) {
            return null;
        }

        return [
            'label' => '🌐 Ask for Website/GBP link',
            'detail' => 'Looking to '.Str::lower($lead->goal->label()).' — grab their link on the next call.',
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function notSure(Lead $lead): ?array
    {
        if ($lead->goal !== LeadGoal::NotSure) {
            return null;
        }

        return [
            'label' => '🎓 Needs Sales — book a meeting',
            'detail' => 'They want expert advice, not a self-serve offer.',
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function welcomeNoReply(Lead $lead): ?array
    {
        if (! $lead->isOverdueForWelcomeReply()) {
            return null;
        }

        return [
            'label' => '💬 Send a WhatsApp check-in',
            'detail' => 'Automated welcome sent '.$lead->welcome_message_sent_at->diffForHumans().' — no reply yet.',
        ];
    }

    /**
     * @return ?array{label: string, detail: ?string}
     */
    private function neverCalled(Lead $lead, ?Collection $bestHours): ?array
    {
        $callLogs = $lead->relationLoaded('callLogs') ? $lead->callLogs : $lead->callLogs()->get();

        if ($callLogs->isNotEmpty()) {
            return null;
        }

        $recommendation = $this->callTiming->recommendationFor($lead, $bestHours);

        if ($recommendation['recommended_label'] === null) {
            return null;
        }

        $label = $recommendation['hours_exhausted']
            ? "📞 Try calling again — best around {$recommendation['recommended_label']}"
            : "📞 Call the lead — best around {$recommendation['recommended_label']}";

        return [
            'label' => $label,
            'detail' => $recommendation['basis_note'],
        ];
    }

    /**
     * Always returns something — the safety net for whatever no other rule
     * covers.
     *
     * @return array{label: string, detail: ?string}
     */
    private function fallbackNote(Lead $lead): array
    {
        $note = $lead->relationLoaded('latestNote') ? $lead->latestNote : $lead->latestNote()->first();

        if ($note === null) {
            return ['label' => '— No activity yet', 'detail' => null];
        }

        return [
            'label' => Str::limit($note->body, 50),
            'detail' => $note->body,
        ];
    }
}
