<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\Note;
use App\Notifications\LeadFollowUpAutoSet;
use App\Services\AnthropicClient;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Plain-note counterpart to DetectCallFollowUpCommitment (2026-08-31) — that
 * job only ever fires for a call logged through the Log a Call form/the
 * "This was a call" note shortcut; a commitment written into an ordinary
 * Lead note ("he said he'll drop by the office Saturday") was never read by
 * anything. Built 2026-09-18 directly from the owner's own examples of what
 * they want the Lead Generation "Next Action" column to say (Remind him to
 * visit the office, Confirm the time to call, etc.) — reuses the exact same
 * grounded, review-not-silent-override contract as the call-log version
 * rather than inventing a separate mechanism.
 *
 * Never overrides a rep-entered next_follow_up_at -- only fills in what was
 * left blank. AI failure (disabled, no API key, malformed reply, no
 * commitment found) is a silent no-op; it must never invent a reminder that
 * isn't really there.
 */
class DetectLeadNoteFollowUpCommitment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $noteId) {}

    public function handle(AnthropicClient $client): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $note = Note::find($this->noteId);

        if ($note === null || ! ($note->notable instanceof Lead) || blank($note->body)) {
            return;
        }

        $lead = $note->notable;

        if ($lead->next_follow_up_at !== null) {
            return;
        }

        $result = $client->message(
            feature: 'lead_note_followup_detection',
            prompt: 'Note: '.$note->body,
            system: $this->system(),
            maxTokens: 300,
        );

        if ($result === null) {
            return;
        }

        $parsed = $this->parse($result->text);

        if ($parsed === null || ! $parsed['has_commitment']) {
            return;
        }

        // Re-check under a fresh read: the rep may have set their own
        // follow-up in the time this job spent waiting in the queue.
        $lead->refresh();

        if ($lead->next_follow_up_at !== null) {
            return;
        }

        $days = max(0, min(30, $parsed['follow_up_in_days'] ?? 3));

        $lead->forceFill([
            'next_follow_up_at' => now()->addDays($days),
            'ai_detected_next_action' => $parsed['next_action'],
        ])->saveQuietly();

        $note->author?->notify(new LeadFollowUpAutoSet($lead));

        // saveQuietly() above means LeadObserver never sees this change —
        // dispatch the fuller re-analysis directly, same as the CallLog
        // version of this job.
        $lead->queueNextActionAnalysis();
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You review a salesperson's note about a lead at a digital-solutions
        agency in India, looking for ONE thing: did the salesperson (or the
        person they were speaking to) commit to a concrete next step -- e.g.
        "he'll visit the office Saturday", "call back to confirm the time",
        "we'll send a proposal", "agreed to a meeting next week". A vague
        "keep in touch" or no clear promise at all does NOT count.

        Respond with ONLY a JSON object, no markdown, no prose:
        {"has_commitment": <true|false>, "follow_up_in_days": <integer 0-14 or null>,
         "next_action": "<short imperative, e.g. 'Confirm office visit time', max 60 chars, or null>"}
        If has_commitment is false, the other two fields must be null.
        PROMPT;
    }

    /**
     * @return array{has_commitment: bool, follow_up_in_days: ?int, next_action: ?string}|null
     */
    private function parse(string $text): ?array
    {
        if (! preg_match('/\{.*\}/s', $text, $match)) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        if (! is_array($decoded) || ! array_key_exists('has_commitment', $decoded)) {
            return null;
        }

        $hasCommitment = filter_var($decoded['has_commitment'], FILTER_VALIDATE_BOOLEAN);

        return [
            'has_commitment' => $hasCommitment,
            'follow_up_in_days' => is_numeric($decoded['follow_up_in_days'] ?? null) ? (int) $decoded['follow_up_in_days'] : null,
            'next_action' => is_string($decoded['next_action'] ?? null) ? mb_substr(trim($decoded['next_action']), 0, 255) : null,
        ];
    }
}
