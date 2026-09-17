<?php

namespace App\Jobs;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Services\AnthropicClient;
use App\Services\VisibilityAuditFunnelMetrics;
use App\Support\Ai;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Reads a lead's FULL history -- every note, every call attempt (with
 * outcome and timestamp), every meeting, its goal/budget/stalling state, and
 * its Visibility Audit/offer funnel stage -- and asks Claude for ONE
 * precise, specific, imperative next action a rep should take right now.
 *
 * Built 2026-09-18 after the owner reviewed three real leads (#435, #372,
 * #340) where LeadNextActionAdvisor's deterministic rules -- each looking at
 * exactly one isolated signal -- produced a stale or generic hint despite
 * the lead's own page telling an obviously more specific story once read
 * end to end. The clearest case: a lead agreed to a Google Meet on one call,
 * then a LATER call attempt went unanswered -- a rule keyed on "the oldest
 * unresolved commitment" kept surfacing the Google Meet line as if nothing
 * had happened since, when the real next step is to retry the call. Only a
 * read of the whole timeline, weighted toward what happened most recently,
 * gets this right.
 *
 * LeadNextActionAdvisor::hintFor() checks this job's cached output FIRST,
 * ahead of every deterministic rule -- those rules remain the fallback for
 * a lead this job hasn't analyzed yet (a brand new lead with no notes/calls
 * yet, or AI disabled). Dispatched via Lead::queueNextActionAnalysis() --
 * see that method's own docblock for the full list of call sites.
 *
 * Same "never break a core workflow" contract as every other AI job in this
 * app: AI failure (disabled, no key, network error, malformed reply) is a
 * silent no-op that leaves whatever hint was already cached (or none) in
 * place, never overwritten with null or garbage.
 */
class AnalyzeLeadNextAction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * How many of the most recent timeline items (notes + calls + meetings,
     * combined and chronologically ordered) to fold into the prompt. Larger
     * than ScoreLead::MAX_ITEMS (5) on purpose -- the whole point of this
     * job is weighing a *sequence* of recent events against each other
     * (e.g. "agreed to X, then Y happened"), not just the single latest
     * item, so it needs enough of the recent narrative to reason about.
     */
    private const MAX_ITEMS = 15;

    public function __construct(public int $leadId) {}

    public function handle(AnthropicClient $client, VisibilityAuditFunnelMetrics $vaFunnel): void
    {
        if (! Ai::enabled()) {
            return;
        }

        $lead = Lead::with(['service', 'notes', 'callLogs', 'meetings'])->find($this->leadId);

        // Lost is the one terminal status worth skipping outright -- nothing
        // useful to tell a rep about a dead lead. Converted is deliberately
        // NOT skipped (despite LeadStatus::isOpen() excluding it too) --
        // "send the quotation" / "schedule the meeting" are exactly the
        // kind of next actions a Converted lead's own Deal needs.
        if ($lead === null || $lead->status === LeadStatus::Lost) {
            return;
        }

        $result = $client->message(
            feature: 'lead_next_action_analysis',
            prompt: $this->prompt($lead, $vaFunnel),
            system: $this->system(),
            maxTokens: 300,
        );

        if ($result === null) {
            return;
        }

        $nextAction = $this->parse($result->text);

        if ($nextAction === null) {
            return;
        }

        // saveQuietly: this is derived/cached state, not a user action --
        // no activity-log entry, and it must not re-trigger itself via
        // LeadObserver::updated() (ai_next_action_hint/_generated_at are
        // already excluded from the trigger fields regardless, but quietly
        // saving keeps this job's own write from firing ANY model event).
        $lead->forceFill([
            'ai_next_action_hint' => $nextAction,
            'ai_next_action_generated_at' => now(),
        ])->saveQuietly();
    }

    private function system(): string
    {
        return <<<'PROMPT'
        You are a sales-operations assistant for a digital-solutions agency in
        India (SEO, GMB, websites, ads, software, AI automation). You will be
        given one sales lead's current state and its full recent timeline --
        notes, call attempts (with outcome), and meetings, oldest to newest.

        Decide the ONE most useful, specific, imperative thing a salesperson
        should do next, right now. Weigh the MOST RECENT timeline items most
        heavily -- a later event supersedes an earlier one whenever they
        conflict. For example, if a lead agreed to a callback or a meeting on
        one call, but a LATER call attempt went unanswered, the real next
        step is to retry the call, not to repeat the earlier commitment as if
        it still stands. If the goal is "Not Sure", the right next step is
        usually to get them on a call or meeting with a sales expert, not to
        ask for a website/GBP link. If the lead reached checkout on a paid
        offer but hasn't paid, and nobody has followed up since, a personal
        nudge about that unfinished purchase is often more useful than a
        generic follow-up.

        Be concrete: name the actual thing to do (e.g. "Call back to confirm
        the Google Meet time" or "Retry calling -- last attempt unanswered"),
        never a vague instruction like "follow up" or "check in." If nothing
        in the timeline suggests a specific action beyond what's already
        obvious from the lead's basic state, it's fine to say so plainly
        (e.g. "Make first contact call").

        Respond with ONLY a JSON object, no markdown, no prose:
        {"next_action": "<short imperative, max 70 chars, or null>"}
        PROMPT;
    }

    private function prompt(Lead $lead, VisibilityAuditFunnelMetrics $vaFunnel): string
    {
        $lines = [
            'Status: '.$lead->status->label(),
            'Goal: '.($lead->goal?->label() ?? 'not captured'),
            'Budget: '.($lead->budget_range?->label() ?? 'not captured'),
            'Service interested in: '.($lead->service?->name ?? 'unspecified'),
            'Website URL on file: '.($lead->website_url ? 'yes' : 'no'),
            'Google Business Profile link on file: '.($lead->gbp_url ? 'yes' : 'no'),
            'Currently tagged stalling on: '.($lead->stall_reason?->label() ?? 'not stalling'),
            'Next follow-up already scheduled: '.($lead->next_follow_up_at?->timezone(config('app.display_timezone'))->format('d M, h:i A') ?? 'none set'),
        ];

        // Reuses VisibilityAuditFunnelMetrics::funnelStatusFor() -- the same
        // single source of truth the Recovery worklist/dashboard/Lead page
        // already render this lead's furthest-reached funnel stage from --
        // rather than re-deriving it here. Null for a lead outside that
        // cohort entirely, so the line is simply omitted.
        $funnelStatus = $vaFunnel->funnelStatusFor($lead);
        if ($funnelStatus !== null) {
            $lines[] = 'Offer funnel stage: '.$funnelStatus['label']
                .($funnelStatus['since'] !== null ? ' ('.$funnelStatus['since']->diffForHumans().')' : '').'.';
        }

        $timeline = $this->timeline($lead);

        if ($timeline->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Timeline (oldest to newest -- weigh the last items most heavily):';
            foreach ($timeline as $item) {
                $lines[] = '- '.$item['at']->timezone(config('app.display_timezone'))->format('d M, h:i A').': '.$item['text'];
            }
        }

        return "Decide the next action for this lead:\n".implode("\n", $lines);
    }

    /**
     * @return Collection<int, array{at: Carbon, text: string}>
     */
    private function timeline(Lead $lead): Collection
    {
        // Each ->map() below still returns an Eloquent\Collection (typed by
        // the original relation), just holding plain arrays now -- that
        // class's own merge() assumes model items ($item->getKey()), a real
        // gotcha this codebase has hit more than once (see
        // [[feedback-gotchas]]). concat()/filter()/sortBy() don't call
        // getKey() so they're safe here regardless, but collect(...->all())
        // downgrades to a plain Support\Collection up front anyway, so nothing
        // later in this chain can accidentally trip over it.
        $notes = collect($lead->notes->all())->map(fn ($note) => [
            'at' => $note->created_at,
            'text' => 'Note: '.$note->body,
        ]);

        $calls = collect($lead->callLogs->all())->map(fn ($call) => [
            'at' => $call->called_at,
            'text' => "Call ({$call->outcome->label()}): ".($call->notes ?: 'no notes')
                .($call->next_action ? " [Logged follow-up: {$call->next_action}]" : ''),
        ]);

        $meetings = collect($lead->meetings->all())->map(fn ($meeting) => [
            'at' => $meeting->occurred_at,
            'text' => 'Meeting "'.($meeting->title ?: 'Meeting').'": '.($meeting->occurred_at?->isFuture() ? 'upcoming' : 'held'),
        ]);

        // Each relation is eager-loaded latest()-first; re-sort ascending so
        // the prompt reads as a real narrative, then keep only the most
        // recent MAX_ITEMS across all three combined -- take(-N) on an
        // ascending collection keeps the LAST (most recent) N while
        // preserving order, same trick used nowhere else in this app but
        // exactly what's needed here (ScoreLead's own take(5) works on a
        // still-descending collection instead).
        return $notes->concat($calls)->concat($meetings)
            ->filter(fn (array $item) => $item['at'] !== null)
            ->sortBy('at')
            ->values()
            ->take(-self::MAX_ITEMS);
    }

    private function parse(string $text): ?string
    {
        if (! preg_match('/\{.*\}/s', $text, $match)) {
            return null;
        }

        $decoded = json_decode($match[0], true);

        if (! is_array($decoded) || ! array_key_exists('next_action', $decoded)) {
            return null;
        }

        $nextAction = $decoded['next_action'];

        if (! is_string($nextAction) || trim($nextAction) === '') {
            return null;
        }

        return mb_substr(trim($nextAction), 0, 80);
    }
}
