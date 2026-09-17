<?php

namespace App\Services;

use App\Enums\LeadGoal;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Lead Generation list's "Next Action" column (2026-09-17 plan, see the
 * [[lead-next-action-column-plan]] memory) — what the CRM thinks a rep
 * should do next for ONE lead, instead of the "Latest Note" column's bare
 * last-note text (often unhelpful at a glance: a raw Meta-import backfill
 * note, a "call not answered" line, or a `[location]` placeholder).
 *
 * Deliberately deterministic, not AI — this renders for every row on every
 * page load, with no per-row API latency/cost, and needs to be as testable
 * as every other rule in this app (owner-confirmed 2026-09-17). Mirrors
 * LeadCallTimingAdvisor's own shape: a plain service, not a NextActionSource
 * (that contract is per-USER — "the one thing to show this person right
 * now" — this is per-LEAD, a column value, not a popup).
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
        return $this->stallOverdue($lead)
            ?? $this->followUpDue($lead)
            ?? $this->meetingSoon($lead)
            ?? $this->needsLink($lead)
            ?? $this->notSure($lead)
            ?? $this->welcomeNoReply($lead)
            ?? $this->neverCalled($lead, $bestHours)
            ?? $this->fallbackNote($lead);
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
     * @return ?array{label: string, detail: ?string}
     */
    private function followUpDue(Lead $lead): ?array
    {
        if ($lead->isFollowUpOverdue()) {
            return [
                'label' => '🔴 Follow up now — overdue',
                'detail' => 'Promised follow-up was due '.$lead->next_follow_up_at->diffForHumans().'.',
            ];
        }

        if ($lead->isFollowUpDueToday()) {
            return [
                'label' => '🟡 Follow up today',
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
            ->filter(fn ($meeting) => $meeting->occurred_at?->isFuture() && $meeting->occurred_at->lte(now()->addHours(self::MEETING_SOON_HOURS)))
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
        $badge = $this->callTiming->badgeLabel($recommendation);

        if ($badge === null) {
            return null;
        }

        return [
            'label' => "📞 {$badge}",
            'detail' => $recommendation['basis_note'],
        ];
    }

    /**
     * Always returns something — the safety net that keeps today's "Latest
     * Note" behavior available even once no other rule fires, per the plan's
     * own phase 1 (side-by-side trial, not a replacement yet).
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
