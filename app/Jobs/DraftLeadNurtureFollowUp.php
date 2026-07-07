<?php

namespace App\Jobs;

use App\Models\Activity;
use App\Models\Lead;
use App\Notifications\LeadNurtureDrafted;
use App\Services\AiAssistant;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drafts one touch of the automated lead nurture sequence and lands it as a
 * staff-only Note on the lead's timeline — never sent automatically, the
 * owner reviews and sends it themselves (same pattern as
 * DraftMonthlyWinsNote). Referenced by lead id, not a serialized model, so a
 * re-run always sees fresh data. AI failure is swallowed — this must never
 * break the nurture command or the lead's own workflow.
 */
class DraftLeadNurtureFollowUp implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ACTIVITY_EVENT = 'lead_nurture_drafted';

    public function __construct(public int $leadId, public int $touch) {}

    public function handle(AiAssistant $ai): void
    {
        if (! Ai::enabled()) {
            return;
        }

        // Idempotency: one draft per lead per touch (defense in depth — the
        // dispatching command already checks this before dispatching).
        if ($this->alreadyDrafted()) {
            return;
        }

        $lead = Lead::with('owner')->find($this->leadId);

        if ($lead === null || $lead->owner === null) {
            return;
        }

        $draft = $ai->draftLeadNurtureFollowUp($lead, $this->touch);

        if ($draft === null) {
            return;
        }

        $lead->notes()->create([
            'user_id' => null,
            'body' => "✨ AI-drafted follow-up (touch {$this->touch}/3) — review before sending:\n\n{$draft}",
        ]);

        Activity::create([
            'user_id' => null,
            'subject_type' => Lead::class,
            'subject_id' => $lead->id,
            'event' => self::ACTIVITY_EVENT,
            'changes' => ['touch' => $this->touch],
        ]);

        $lead->owner->notify(new LeadNurtureDrafted($lead, $this->touch));
    }

    private function alreadyDrafted(): bool
    {
        return Activity::where('subject_type', Lead::class)
            ->where('subject_id', $this->leadId)
            ->where('event', self::ACTIVITY_EVENT)
            ->whereJsonContains('changes->touch', $this->touch)
            ->exists();
    }
}
