<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Deal;
use App\Notifications\DealStallFollowUpDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drafts a check-in for a Deal that's gone quiet mid-pipeline and lands it
 * as a staff-only Note -- never sent automatically, the owner reviews and
 * sends it themselves (same pattern as DraftLeadNurtureFollowUp). Referenced
 * by deal id, not a serialized model, so a re-run always sees fresh data. AI
 * failure is swallowed -- this must never break the drafting command or the
 * deal's own workflow.
 */
class DraftDealStallFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTIVITY_EVENT = 'deal_stall_followup_drafted';

    public function __construct(public int $dealId) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $deal = Deal::with('owner')->find($this->dealId);

        if ($deal === null || $deal->owner === null) {
            return;
        }

        // Idempotency: one draft per stale period (defense in depth -- the
        // dispatching command already checks this before dispatching).
        if ($this->alreadyDrafted($deal)) {
            return;
        }

        $daysSinceLastTouch = $deal->lastTouchedAt()->diffInDays(now());

        $draft = $ai->draftDealStallFollowUp($deal, $daysSinceLastTouch);

        if ($draft === null) {
            return;
        }

        $deal->notes()->create([
            'user_id' => null,
            'body' => "✨ AI-drafted check-in (deal gone quiet) — review before sending:\n\n{$draft}",
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Deal::class,
            'subject_id' => $deal->id,
            'event' => self::ACTIVITY_EVENT,
            'changes' => null,
        ]);

        $deal->owner->notify(new DealStallFollowUpDrafted($deal));
    }

    /**
     * Re-fires once a genuine new touch (a note, or a logged edit) supersedes
     * the last time this was drafted -- not a one-shot-forever flag like
     * DraftLeadNurtureFollowUp's per-touch marker, since a deal (unlike a
     * New lead on a fixed 1/3/7-day cadence) can go quiet, get worked, then
     * go quiet again indefinitely. The marker Activity row this job itself
     * writes is one of the rows lastTouchedAt() considers, so once written
     * it correctly counts as "handled" until a real new touch arrives.
     */
    private function alreadyDrafted(Deal $deal): bool
    {
        return Activity::where('subject_type', Deal::class)
            ->where('subject_id', $deal->id)
            ->where('event', self::ACTIVITY_EVENT)
            ->where('created_at', '>=', $deal->lastTouchedAt())
            ->exists();
    }
}
