<?php

namespace App\Jobs;

use App\Models\CallLog;
use App\Notifications\CallFollowUpAutoSet;
use App\Services\AnthropicClient;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Catches the case that prompted this feature (2026-08-31): a call that
 * genuinely connects and goes well often ends in an unstated promise ("I'll
 * send a proposal") -- exactly the outcome a rep is least likely to think to
 * schedule a reminder for, since nothing about the call felt like it needed
 * one. Reads the just-saved notes, and if Claude finds a clear commitment
 * AND the rep didn't already set their own follow_up_at/next_action, sets
 * both directly and notifies the rep (CallFollowUpAutoSet) so they can
 * review/adjust rather than being surprised later.
 *
 * Never overrides a rep-entered value -- this only fills in what was left
 * blank. AI failure (disabled, no API key, malformed reply, no commitment
 * found) is a silent no-op; it must never invent a reminder that isn't
 * really there.
 */
class DetectCallFollowUpCommitment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $callLogId) {}

    public function handle(AnthropicClient $client): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $call = CallLog::with('callable')->find($this->callLogId);

        if ($call === null || $call->follow_up_at !== null || blank($call->notes)) {
            return;
        }

        $result = $client->message(
            feature: 'call_followup_detection',
            prompt: $this->prompt($call),
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

        // Re-check under a fresh read: the rep may have added their own
        // reminder in the time this job spent waiting in the queue.
        $call->refresh();

        if ($call->follow_up_at !== null) {
            return;
        }

        $days = max(0, min(30, $parsed['follow_up_in_days'] ?? 3));

        $call->forceFill([
            'follow_up_at' => $call->called_at->copy()->addDays($days),
            'next_action' => $call->next_action ?: $parsed['next_action'],
        ])->saveQuietly();

        $call->user?->notify(new CallFollowUpAutoSet($call));
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You review a salesperson's call notes at a digital-solutions agency
        in India, looking for ONE thing: did the salesperson (or the person
        they were speaking to) commit to a concrete next step -- e.g. "we'll
        send a proposal", "I'll call back next week", "will share pricing",
        "agreed to a meeting". A vague "keep in touch" or no clear promise at
        all does NOT count.

        Respond with ONLY a JSON object, no markdown, no prose:
        {"has_commitment": <true|false>, "follow_up_in_days": <integer 0-14 or null>,
         "next_action": "<short imperative, e.g. 'Send proposal', max 60 chars, or null>"}
        If has_commitment is false, the other two fields must be null.
        PROMPT;
    }

    private function prompt(CallLog $call): string
    {
        return implode("\n", [
            'Outcome: '.$call->outcome->label(),
            'Duration: '.($call->duration_minutes !== null ? $call->duration_minutes.' min' : 'unknown'),
            'Notes: '.$call->notes,
        ]);
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
