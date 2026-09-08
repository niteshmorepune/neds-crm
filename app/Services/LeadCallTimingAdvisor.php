<?php

namespace App\Services;

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Models\CallLog;
use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Per-lead "best time to call" — deliberately NOT a per-lead statistical
 * model. A single lead typically has only 1-5 logged call attempts total
 * (see CallTimingMetrics' own doc comment: even the whole team's per-weekday
 * samples, 27-104 calls, were judged too thin to trust), so computing a
 * from-scratch connect-rate for one lead would almost always be noise
 * dressed up as a finding. Instead this combines three honest, always-valid
 * signals: the lead's own raw attempt history (what was actually tried, and
 * what happened); when the lead itself was captured — for a source where
 * that timestamp reflects the prospect's own action (LeadSource::
 * isProspectInitiated()), not a rep entering data whenever suited them —
 * as a real personal availability signal, most valuable for a lead with no
 * call attempts of its own yet; and CallTimingMetrics' team-wide best-hour
 * band (the one pattern with a real sample size) for the next attempt.
 * Any hour already tried twice+ against THIS lead with no answer is
 * excluded from the recommendation regardless of which signal produced it.
 * Confirmed with the owner via AskUserQuestion (2026-09-08) over building
 * true per-lead statistics or a lead-source/service-level tier; the capture-
 * time signal was added the same day at the owner's suggestion.
 */
class LeadCallTimingAdvisor
{
    /** An hour tried this many times against one lead with no Connected outcome is excluded from the recommendation. */
    private const EXCLUDE_AFTER_FAILED_ATTEMPTS = 2;

    public function __construct(private readonly CallTimingMetrics $global) {}

    /**
     * @param  Collection<int, array{hour: int, total: int, connected: int, rate: float}>|null  $bestHours  Pass a pre-computed CallTimingMetrics::bestHours() when calling this in a loop (list/queue pages) so the underlying query only runs once per request.
     * @return array{
     *     own_attempts: Collection<int, array{called_at: Carbon, hour: int, outcome: CallOutcome}>,
     *     own_attempt_count: int,
     *     failed_hours: list<int>,
     *     ever_connected: bool,
     *     connected_hours: list<int>,
     *     capture_hour: ?array{hour: int, label: string, source_label: string},
     *     recommended_hours: list<int>,
     *     recommended_label: ?string,
     *     hours_exhausted: bool,
     *     basis_note: string,
     * }
     */
    public function recommendationFor(Lead $lead, ?Collection $bestHours = null): array
    {
        $bestHours ??= $this->global->bestHours();

        $ownCalls = ($lead->relationLoaded('callLogs') ? $lead->callLogs : $lead->callLogs()->get())
            ->filter(fn (CallLog $call) => $call->direction === CallDirection::Outgoing && $call->called_at !== null)
            ->sortByDesc('called_at')
            ->values();

        $withHour = $ownCalls->map(fn (CallLog $call) => [
            'called_at' => $call->called_at,
            'hour' => (int) $call->called_at->clone()->timezone(config('app.display_timezone'))->format('H'),
            'outcome' => $call->outcome,
        ]);

        $failedHours = $withHour
            ->groupBy('hour')
            ->filter(fn (Collection $atHour) => $atHour->count() >= self::EXCLUDE_AFTER_FAILED_ATTEMPTS
                && ! $atHour->contains(fn (array $c) => $c['outcome'] === CallOutcome::Connected))
            ->keys()
            ->map(fn ($hour) => (int) $hour)
            ->values()
            ->all();

        $connectedHours = $withHour
            ->where('outcome', CallOutcome::Connected)
            ->pluck('hour')
            ->unique()
            ->values()
            ->all();

        $captureHour = $this->captureHour($lead);

        // bestHours() is ordered by connect rate, not hour — sort numerically
        // here so the recommendation (and its rendered label) is always in a
        // predictable, chronological order regardless of tie-breaking there.
        $globalHours = $bestHours->pluck('hour')->sort()->values()->all();

        if ($ownCalls->isEmpty() && $captureHour !== null) {
            // Zero real signal for this lead otherwise — when they reached
            // out is a far more specific, personal signal than the team's
            // generic multi-hour band, so don't dilute it into that band.
            $candidatePool = [$captureHour['hour']];
        } else {
            $candidatePool = collect($globalHours)
                ->when($captureHour !== null, fn (Collection $c) => $c->push($captureHour['hour']))
                ->unique()
                ->sort()
                ->values()
                ->all();
        }

        $remainingHours = collect($candidatePool)->diff($failedHours)->values()->all();
        $hoursExhausted = $candidatePool !== [] && $remainingHours === [];
        $recommendedHours = $hoursExhausted ? $candidatePool : $remainingHours;

        $fmt = fn (int $hour) => Carbon::createFromTime($hour, 0)->format('g A');
        $recommendedLabel = $recommendedHours === []
            ? null
            : collect($recommendedHours)->sort()->map($fmt)->implode(', ');

        return [
            'own_attempts' => $withHour->take(10),
            'own_attempt_count' => $ownCalls->count(),
            'failed_hours' => $failedHours,
            'ever_connected' => $connectedHours !== [],
            'connected_hours' => $connectedHours,
            'capture_hour' => $captureHour,
            'recommended_hours' => $recommendedHours,
            'recommended_label' => $recommendedLabel,
            'hours_exhausted' => $hoursExhausted,
            'basis_note' => $this->basisNote($ownCalls->count(), $candidatePool, $hoursExhausted, $captureHour),
        ];
    }

    /**
     * @return ?array{hour: int, label: string, source_label: string}
     */
    private function captureHour(Lead $lead): ?array
    {
        if (! $lead->source->isProspectInitiated()) {
            return null;
        }

        $hour = (int) $lead->created_at->clone()->timezone(config('app.display_timezone'))->format('H');

        return [
            'hour' => $hour,
            'label' => Carbon::createFromTime($hour, 0)->format('g A'),
            'source_label' => $lead->source->label(),
        ];
    }

    private function basisNote(int $ownAttemptCount, array $candidatePool, bool $hoursExhausted, ?array $captureHour): string
    {
        return match (true) {
            $candidatePool === [] => 'Not enough team-wide call data yet to suggest a time.',
            $ownAttemptCount === 0 && $captureHour !== null => "No calls logged to this lead yet — it came in via {$captureHour['source_label']} around {$captureHour['label']}, worth trying near then.",
            $ownAttemptCount === 0 => 'No calls logged to this lead yet — based on team-wide calling patterns.',
            $hoursExhausted => 'Every usually-good hour has already been tried with this lead — worth trying again, or a different day.',
            $captureHour !== null => "Based on {$ownAttemptCount} past attempt(s) to this lead (came in via {$captureHour['source_label']} around {$captureHour['label']}), plus team-wide calling patterns.",
            default => "Based on {$ownAttemptCount} past attempt(s) to this lead, plus team-wide calling patterns.",
        };
    }

    /**
     * Compact one-liner for a list row / queue item. Null when there's
     * nothing worth showing (no global data at all yet).
     */
    public function badgeLabel(array $recommendation): ?string
    {
        if ($recommendation['recommended_label'] === null) {
            return null;
        }

        return $recommendation['hours_exhausted']
            ? "Retry: {$recommendation['recommended_label']}"
            : "Try: {$recommendation['recommended_label']}";
    }
}
