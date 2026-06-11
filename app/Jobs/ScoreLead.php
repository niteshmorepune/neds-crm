<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Services\AnthropicClient;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scores a lead 0–100 with a one-line reason using Claude, on create/update.
 *
 * Queued (database driver on shared hosting). The lead is referenced by id, not
 * a serialized model, so a re-score always runs against fresh data and a deleted
 * lead is a no-op. AI failure is swallowed — scoring must never break the lead
 * workflow.
 */
class ScoreLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $leadId) {}

    public function handle(AnthropicClient $client): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $lead = Lead::with('service')->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $result = $client->message(
            feature: 'lead_scoring',
            prompt: $this->prompt($lead),
            system: $this->system(),
            maxTokens: 1000,
        );

        if ($result === null) {
            return;
        }

        $parsed = $this->parse($result->text);

        if ($parsed === null) {
            return;
        }

        // saveQuietly: don't fire model events — avoids both an activity-log
        // entry and a re-dispatch of this very job.
        $lead->forceFill([
            'ai_score' => $parsed['score'],
            'ai_score_reason' => $parsed['reason'],
            'ai_scored_at' => now(),
        ])->saveQuietly();
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You are a sales-qualification assistant for a digital-solutions agency in
        India (SEO, websites, ads, software, AI automation). Score how promising a
        sales lead is from 0 (cold) to 100 (hot), based on the detail provided.

        Respond with ONLY a JSON object, no markdown, no prose:
        {"score": <integer 0-100>, "reason": "<one short sentence, max 120 chars>"}
        PROMPT;
    }

    private function prompt(Lead $lead): string
    {
        $lines = [
            'Name: '.($lead->name ?: 'unknown'),
            'Company: '.($lead->company ?: 'unknown'),
            'Email: '.($lead->email ?: 'none'),
            'Phone: '.($lead->phone ?: 'none'),
            'Source: '.$lead->source->label(),
            'Service interested in: '.($lead->service?->name ?? 'unspecified'),
            'Estimated value (INR): '.number_format($lead->estimated_value / 100, 2),
        ];

        return "Score this lead:\n".implode("\n", $lines);
    }

    /**
     * Parse the model's JSON reply leniently. Returns null if no usable score is
     * found, so a malformed response leaves the lead unscored rather than wrong.
     *
     * @return array{score: int, reason: string}|null
     */
    private function parse(string $text): ?array
    {
        if (! preg_match('/\{.*\}/s', $text, $match)) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        if (! is_array($decoded) || ! isset($decoded['score']) || ! is_numeric($decoded['score'])) {
            return null;
        }

        $score = (int) max(0, min(100, (int) $decoded['score']));
        $reason = is_string($decoded['reason'] ?? null)
            ? mb_substr(trim($decoded['reason']), 0, 255)
            : null;

        return ['score' => $score, 'reason' => $reason];
    }
}
