<?php

namespace App\Services;

use App\Enums\CallOutcome;
use App\Models\CallLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Real connect-rate-by-hour patterns from CallLog, computed over a trailing
 * window (not all-time) so the guidance tracks recent team behavior instead
 * of staying anchored to a since-improved early period — a real diagnostic
 * run 2026-08-28 found the connect rate rose from ~52% the week of Jul 27 to
 * ~94% the week of Aug 24, and that late-morning (11 AM) and after-4-PM
 * calls connect notably worse (~55-70%) than 9-10 AM or midday (~78-83%),
 * even though the aggregate connect rate overall looked fine (~74%). Powers
 * the "best time to call" hint and the smarter no-answer retry-time
 * suggestion on the Log a Call form.
 *
 * Deliberately does NOT rank by day-of-week — the sample per weekday
 * (27-104 calls over ~11 weeks) is thin enough to plausibly reflect which
 * rep/campaign happened to dial that day rather than a real day-of-week
 * effect, unlike the much larger per-hour buckets. Baking a shaky "avoid
 * Thursday" rule into an auto-suggested time would risk teaching a
 * confound, not a real pattern.
 */
class CallTimingMetrics
{
    /** Below this many logged calls in an hour, its rate isn't trusted enough to act on. */
    private const MIN_SAMPLE = 15;

    public function __construct(private readonly int $windowDays = 90) {}

    /**
     * @return Collection<int, array{hour: int, total: int, connected: int, rate: float}>
     */
    public function connectRateByHour(): Collection
    {
        $rows = CallLog::where('direction', 'outgoing')
            ->where('called_at', '>=', now()->subDays($this->windowDays))
            ->get(['outcome', 'called_at']);

        $byHour = [];
        foreach ($rows as $row) {
            if ($row->called_at === null) {
                continue;
            }

            $hour = (int) $row->called_at->clone()->timezone('Asia/Kolkata')->format('H');
            $byHour[$hour]['total'] = ($byHour[$hour]['total'] ?? 0) + 1;

            if ($row->outcome === CallOutcome::Connected) {
                $byHour[$hour]['connected'] = ($byHour[$hour]['connected'] ?? 0) + 1;
            }
        }

        return collect($byHour)->map(fn (array $d, int $hour) => [
            'hour' => $hour,
            'total' => $d['total'],
            'connected' => $d['connected'] ?? 0,
            'rate' => $d['total'] ? round(($d['connected'] ?? 0) / $d['total'] * 100, 1) : 0.0,
        ])->values();
    }

    /** Hours with enough logged calls to trust, best connect rate first. */
    public function bestHours(): Collection
    {
        return $this->connectRateByHour()
            ->where('total', '>=', self::MIN_SAMPLE)
            ->sortByDesc('rate')
            ->values();
    }

    /**
     * One-line, human-readable "best time to call" summary for the Log a
     * Call form — null when there isn't enough data yet to say anything
     * trustworthy (never shows a guess dressed up as a finding).
     */
    public function summaryLine(): ?string
    {
        $ranked = $this->bestHours();

        if ($ranked->count() < 3) {
            return null;
        }

        $fmt = fn (int $hour) => Carbon::createFromTime($hour, 0)->format('g A');

        $best = $ranked->take(3)->sortBy('hour');
        $worst = $ranked->reverse()->take(3)->sortBy('hour');

        $bestList = $best->map(fn (array $r) => $fmt($r['hour']))->implode(', ');
        $worstList = $worst->map(fn (array $r) => $fmt($r['hour']))->implode(', ');

        return sprintf(
            'Based on %d logged calls (last %d days): connect rates are highest around %s (~%s%%), lowest around %s (~%s%%).',
            $ranked->sum('total'),
            $this->windowDays,
            $bestList,
            $best->max('rate'),
            $worstList,
            $worst->min('rate'),
        );
    }

    /**
     * Next Asia/Kolkata datetime at or after $notBefore whose hour is among
     * the best-performing hours — used to pre-fill a smarter retry time
     * after a No Answer/Busy, instead of leaving the rep to guess or
     * defaulting to "same time tomorrow." Never suggests a Sunday — zero
     * outbound calls have ever been logged on one, matching the team's real
     * working pattern rather than an assumption. Returns null when there
     * isn't enough data to trust a suggestion, or no qualifying hour falls
     * within the next 14 days (should not happen in practice).
     */
    public function suggestNextCallSlot(Carbon $notBefore): ?Carbon
    {
        $bestHourNumbers = $this->bestHours()->pluck('hour')->all();

        if ($bestHourNumbers === []) {
            return null;
        }

        $candidate = $notBefore->clone()->timezone('Asia/Kolkata')->addHour()->startOfHour();

        for ($i = 0; $i < 14 * 24; $i++) {
            $isSunday = $candidate->dayOfWeek === Carbon::SUNDAY;

            if (! $isSunday && in_array((int) $candidate->format('H'), $bestHourNumbers, true)) {
                return $candidate;
            }

            $candidate->addHour();
        }

        return null;
    }
}
