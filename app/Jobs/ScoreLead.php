<?php

namespace App\Jobs;

use App\Enums\LeadBudgetBand;
use App\Enums\LeadUrgency;
use App\Models\Lead;
use App\Notifications\HotLeadNotification;
use App\Services\AnthropicClient;
use App\Services\VisibilityAuditFunnelMetrics;
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
 *
 * Dispatch sites: LeadObserver (create/scoring-field update/close), RecordNotes
 * (a note added), CallLogController (a call logged), and — for a lead in the
 * Visibility Audit cohort — VisibilityAuditFunnelTrackingController (landing
 * page viewed, checkout reached) and RecordVisibilityAuditPurchase (paid).
 * The prompt itself folds in the lead's current VA funnel stage via
 * VisibilityAuditFunnelMetrics::funnelStatusFor() regardless of which of
 * those sites triggered this particular re-score.
 */
class ScoreLead implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * How many recent notes/calls to fold into the prompt -- bounded so a
     * lead with a long history doesn't blow out prompt size, same limit
     * AiAssistant::prepareCallBrief() uses for the same kind of section.
     */
    private const MAX_ITEMS = 5;

    public function __construct(public int $leadId) {}

    public function handle(AnthropicClient $client, VisibilityAuditFunnelMetrics $vaFunnel): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $lead = Lead::with(['service', 'notes', 'callLogs'])->find($this->leadId);

        if ($lead === null) {
            return;
        }

        $result = $client->message(
            feature: 'lead_scoring',
            prompt: $this->prompt($lead, $vaFunnel),
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
            'ai_budget_band' => $parsed['budget_band'],
            'ai_urgency' => $parsed['urgency'],
            'ai_service_fit' => $parsed['service_fit'],
        ])->saveQuietly();

        if ($lead->isHot() && $lead->owner_id) {
            $lead->owner?->notify(new HotLeadNotification($lead));
        }
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You are a sales-qualification assistant for a digital-solutions agency in
        India (SEO, websites, ads, software, AI automation). Score how promising a
        sales lead is from 0 (cold) to 100 (hot), based on the detail provided.
        Also estimate their likely budget band, how urgent their need seems, and
        whether the service they asked about is a good fit for their situation.

        If Notes or Call history are included, weigh them heavily -- they reflect
        what actually happened after the lead first came in, and are a much
        stronger signal than the original intake form. A lead who took a real,
        engaged call and asked specific questions (pricing, timeline, how it
        works) or agreed to a next step is warmer than the bare form fields
        alone would suggest, even if no exact budget number was ever stated.
        "Estimated value: not provided" means nobody has entered one yet -- it
        is NOT the same as a confirmed zero-value deal, and must not be
        treated as a disqualifying signal on its own, especially when Notes or
        Call history show real engagement.

        If a Visibility Audit funnel status is included, it reflects the
        lead's own self-directed behaviour on a paid offer page -- weigh it
        comparably to Notes/Calls, not as a minor detail. "Paid" is the
        strongest possible signal (this person has already spent real money);
        "Reached checkout" is a strong positive signal, stronger than an
        untouched intake form alone, since they were seconds from paying;
        "Viewed the offer page" is a moderate positive signal. Being merely
        "invited" or "eligible" with no engagement yet is neutral and must not
        by itself raise or lower the score.

        Respond with ONLY a JSON object, no markdown, no prose:
        {"score": <integer 0-100>, "reason": "<one short sentence, max 120 chars>",
         "budget_band": "<low|medium|high>", "urgency": "<low|medium|high>",
         "service_fit": "<one short sentence, max 140 chars>"}
        PROMPT;
    }

    private function prompt(Lead $lead, VisibilityAuditFunnelMetrics $vaFunnel): string
    {
        $lines = [
            'Name: '.($lead->name ?: 'unknown'),
            'Company: '.($lead->company ?: 'unknown'),
            'Email: '.($lead->email ?: 'none'),
            'Phone: '.($lead->phone ?: 'none'),
            'Source: '.$lead->source->label(),
            'Service interested in: '.($lead->service?->name ?? 'unspecified'),
            // A genuinely null estimate must read as "not provided", not a
            // literal "0.00" -- printing 0.00 tells the model this deal is
            // confirmed worthless, a much stronger disqualifying signal than
            // "nobody has estimated it yet" (real production case, 2026-08-31:
            // a lead with an engaged, connected call still scored 15 because
            // the prompt said "Estimated value (INR): 0.00").
            'Estimated value (INR): '.($lead->estimated_value === null ? 'not provided' : number_format($lead->estimated_value / 100, 2)),
        ];

        // Reuses VisibilityAuditFunnelMetrics::funnelStatusFor() -- the same
        // single source of truth the Recovery worklist/dashboard/Lead page
        // already render this lead's furthest-reached stage from -- rather
        // than re-deriving funnel state independently here. Null for a lead
        // outside the VA cohort entirely, so this line is simply omitted.
        $funnelStatus = $vaFunnel->funnelStatusFor($lead);
        if ($funnelStatus !== null) {
            $lines[] = 'Visibility Audit funnel status: '.$funnelStatus['label']
                .($funnelStatus['since'] !== null ? ' ('.$funnelStatus['since']->diffForHumans().')' : '').'.';
        }

        if ($lead->notes->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Notes (most recent first):';
            foreach ($lead->notes->take(self::MAX_ITEMS) as $note) {
                $lines[] = '- '.$note->body;
            }
        }

        if ($lead->callLogs->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Call history (most recent first):';
            foreach ($lead->callLogs->take(self::MAX_ITEMS) as $call) {
                $lines[] = "- {$call->direction->label()} / {$call->outcome->label()}: ".($call->notes ?: 'no notes');
            }
        }

        return "Score this lead:\n".implode("\n", $lines);
    }

    /**
     * Parse the model's JSON reply leniently. Returns null if no usable score is
     * found, so a malformed response leaves the lead unscored rather than wrong.
     * The newer fields (budget_band, urgency, service_fit) are best-effort: an
     * invalid or missing value becomes null rather than failing the whole parse.
     *
     * @return array{score: int, reason: ?string, budget_band: ?string, urgency: ?string, service_fit: ?string}|null
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

        return [
            'score' => $score,
            'reason' => $reason,
            'budget_band' => $this->parseEnumValue($decoded['budget_band'] ?? null, LeadBudgetBand::class),
            'urgency' => $this->parseEnumValue($decoded['urgency'] ?? null, LeadUrgency::class),
            'service_fit' => is_string($decoded['service_fit'] ?? null)
                ? mb_substr(trim($decoded['service_fit']), 0, 255)
                : null,
        ];
    }

    /**
     * @param  class-string<LeadBudgetBand|LeadUrgency>  $enum
     */
    private function parseEnumValue(mixed $value, string $enum): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        return $enum::tryFrom(strtolower(trim($value)))?->value;
    }
}
