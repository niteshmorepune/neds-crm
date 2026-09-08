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
 * dressed up as a finding. Instead this combines two honest, always-valid
 * signals: the lead's own raw attempt history (what was actually tried, and
 * what happened) plus CallTimingMetrics' team-wide best-hour band (the one
 * pattern with a real sample size) for the next attempt — excluding any hour
 * already tried twice+ against THIS lead with no answer. Confirmed with the
 * owner via AskUserQuestion (2026-09-08) over building true per-lead
 * statistics or a lead-source/service-level tier.
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

        // bestHours() is ordered by connect rate, not hour — sort numerically
        // here so the recommendation (and its rendered label) is always in a
        // predictable, chronological order regardless of tie-breaking there.
        $globalHours = $bestHours->pluck('hour')->sort()->values()->all();
        $remainingHours = collect($globalHours)->diff($failedHours)->values()->all();

        $hoursExhausted = $globalHours !== [] && $remainingHours === [];
        $recommendedHours = $hoursExhausted ? $globalHours : $remainingHours;

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
            'recommended_hours' => $recommendedHours,
            'recommended_label' => $recommendedLabel,
            'hours_exhausted' => $hoursExhausted,
            'basis_note' => $this->basisNote($ownCalls->count(), $globalHours, $hoursExhausted),
        ];
    }

    private function basisNote(int $ownAttemptCount, array $globalHours, bool $hoursExhausted): string
    {
        return match (true) {
            $globalHours === [] => 'Not enough team-wide call data yet to suggest a time.',
            $ownAttemptCount === 0 => 'No calls logged to this lead yet — based on team-wide calling patterns.',
            $hoursExhausted => 'Every usually-good hour has already been tried with this lead — worth trying again, or a different day.',
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
