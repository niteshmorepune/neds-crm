<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Jobs\MuteWadeskConversationJob;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\PossibleDuplicateLeadNotification;
use App\Services\DuplicateLeadDetector;

/**
 * Runs immediately after WhatsappWebhookController::handleUnmatchedNumber()
 * creates a brand-new Lead — a side effect, never a precondition for Lead
 * creation or the WhatsApp reply pipeline (both already happened/are
 * already in flight by the time this runs). See DuplicateLeadDetector's own
 * docblock for why this can only ever be an after-the-fact alert, not a
 * reply gate.
 *
 * One notification per detected pair: duplicate_flagged_at is a one-shot
 * guard, so calling this again for a Lead that's already been flagged (or
 * whose candidate check already ran and found nothing) is a no-op. In
 * practice this method is only ever reached once per Lead already — see
 * handleUnmatchedNumber()'s own docblock, a later message on the same
 * conversation never re-enters the Lead::create() branch — but the guard
 * makes that a structural guarantee rather than an incidental one.
 */
class FlagPossibleDuplicateLead
{
    public function __construct(private readonly DuplicateLeadDetector $detector) {}

    public function handle(Lead $lead): void
    {
        if ($lead->duplicate_flagged_at !== null) {
            return;
        }

        $candidate = $this->detector->findCandidate($lead);

        if ($candidate === null) {
            return;
        }

        $lead->forceFill([
            'possible_duplicate_of_lead_id' => $candidate->id,
            'duplicate_flagged_at' => now(),
        ])->saveQuietly();

        $this->notifyStaff($lead, $candidate);

        // Best-effort (Task 2) — stops the NEXT automated reply on this
        // conversation. The auto-reply that already fired before this ran
        // is unaffected; see MuteWadeskConversationJob's own docblock.
        if (filled($lead->whatsapp_conversation_id)) {
            MuteWadeskConversationJob::dispatch($lead->whatsapp_conversation_id);
        }
    }

    private function notifyStaff(Lead $newLead, Lead $olderLead): void
    {
        $notification = new PossibleDuplicateLeadNotification($newLead, $olderLead);

        User::where('is_active', true)
            ->whereIn('role', [UserRole::Admin->value, UserRole::Manager->value])
            ->get()
            ->each(fn (User $user) => $user->notify($notification));
    }
}
