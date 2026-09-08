<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Lead;
use App\Notifications\LeadStallFollowUpDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Lead-side counterpart to App\Jobs\DraftDealStallFollowUp -- mirrors it
 * exactly (staff-only Note, never sent automatically, owner reviews and
 * sends). See that class's own docblock for the full reasoning.
 */
class DraftLeadStallFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTIVITY_EVENT = 'lead_stall_followup_drafted';

    public function __construct(public int $leadId) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $lead = Lead::with('owner')->find($this->leadId);

        if ($lead === null || $lead->owner === null) {
            return;
        }

        // Idempotency: one draft per stale period (defense in depth -- the
        // dispatching command already checks this before dispatching).
        if ($this->alreadyDrafted($lead)) {
            return;
        }

        $daysSinceLastTouch = $lead->lastTouchedAt()->diffInDays(now());

        $draft = $ai->draftLeadStallFollowUp($lead, $daysSinceLastTouch);

        if ($draft === null) {
            return;
        }

        $lead->notes()->create([
            'user_id' => null,
            'body' => "✨ AI-drafted check-in (lead gone quiet) — review before sending:\n\n{$draft}",
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'event' => self::ACTIVITY_EVENT,
            'changes' => null,
        ]);

        $lead->owner->notify(new LeadStallFollowUpDrafted($lead));
    }

    /**
     * Re-fires once a genuine new touch supersedes the last time this was
     * drafted -- see DraftDealStallFollowUp::alreadyDrafted()'s own
     * docblock. The marker Activity row this job itself writes is one of
     * the rows lastTouchedAt() considers, so once written it correctly
     * counts as "handled" until a real new touch arrives.
     */
    private function alreadyDrafted(Lead $lead): bool
    {
        return Activity::where('subject_type', Lead::class)
            ->where('subject_id', $lead->id)
            ->where('event', self::ACTIVITY_EVENT)
            ->where('created_at', '>=', $lead->lastTouchedAt())
            ->exists();
    }
}
