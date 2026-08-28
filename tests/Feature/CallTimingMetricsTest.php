<?php

use App\Enums\CallOutcome;
use App\Models\CallLog;
use App\Services\CallTimingMetrics;
use Illuminate\Support\Carbon;

/**
 * Builds a CallLog at a specific Asia/Kolkata hour, $daysAgo days back from
 * now, mirroring how CallLogController::store() itself converts the
 * display-timezone input to UTC before saving.
 */
function callAt(int $hour, int $daysAgo, CallOutcome $outcome = CallOutcome::Connected): CallLog
{
    $calledAt = Carbon::now('Asia/Kolkata')->subDays($daysAgo)->setTime($hour, 0, 0)->utc();

    return CallLog::factory()->create([
        'direction' => 'outgoing',
        'outcome' => $outcome,
        'called_at' => $calledAt,
    ]);
}

it('computes connect rate by hour in Asia/Kolkata, not UTC', function () {
    // 09:00 IST = 03:30 UTC — proves the hour bucket follows IST.
    callAt(9, 1, CallOutcome::Connected);
    callAt(9, 2, CallOutcome::NoAnswer);

    $byHour = app(CallTimingMetrics::class)->connectRateByHour();
    $nine = $byHour->firstWhere('hour', 9);

    expect($nine['total'])->toBe(2)
        ->and($nine['connected'])->toBe(1)
        ->and($nine['rate'])->toBe(50.0);
});

it('excludes hours below the minimum sample size from bestHours()', function () {
    // 14 calls at hour 9 (below the 15-call threshold), all connected.
    for ($i = 1; $i <= 14; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }

    expect(app(CallTimingMetrics::class)->bestHours())->toBeEmpty();
});

it('ranks bestHours() by connect rate, descending, once the sample qualifies', function () {
    // Hour 9: 15 calls, 15 connected (100%).
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }
    // Hour 11: 15 calls, 5 connected (33.3%).
    for ($i = 1; $i <= 10; $i++) {
        callAt(11, $i, CallOutcome::NoAnswer);
    }
    for ($i = 11; $i <= 15; $i++) {
        callAt(11, $i, CallOutcome::Connected);
    }

    $best = app(CallTimingMetrics::class)->bestHours();

    expect($best->first()['hour'])->toBe(9)
        ->and($best->first()['rate'])->toBe(100.0)
        ->and($best->last()['hour'])->toBe(11);
});

it('ignores calls older than the trailing window', function () {
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }
    // Same hour, but well outside a 30-day window.
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, 60 + $i, CallOutcome::NoAnswer);
    }

    $best = (new CallTimingMetrics(windowDays: 30))->bestHours();

    // Only the recent, all-connected batch counts within the 30-day window.
    expect($best->firstWhere('hour', 9)['rate'])->toBe(100.0);
});

it('returns null from summaryLine() when there is not enough qualifying data yet', function () {
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }

    // Only 1 qualifying hour — summaryLine() requires at least 3.
    expect(app(CallTimingMetrics::class)->summaryLine())->toBeNull();
});

it('describes the best and worst qualifying hours in summaryLine()', function () {
    foreach ([9, 10, 12, 11, 14, 16] as $hour) {
        for ($i = 1; $i <= 15; $i++) {
            callAt($hour, $i, in_array($hour, [9, 10, 12], true) ? CallOutcome::Connected : CallOutcome::NoAnswer);
        }
    }

    $summary = app(CallTimingMetrics::class)->summaryLine();

    expect($summary)->toContain('9 AM')->toContain('10 AM')->toContain('12 PM')
        ->and($summary)->toContain('90 days');
});

it('suggests the next best-hour slot after a missed call', function () {
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }

    // A no-answer at 11:00 AM on a Monday — the next 9 AM slot should be tomorrow.
    $notBefore = Carbon::parse('2026-08-24 11:00:00', 'Asia/Kolkata'); // a Monday
    $slot = app(CallTimingMetrics::class)->suggestNextCallSlot($notBefore);

    expect($slot->timezone('Asia/Kolkata')->format('Y-m-d H:i'))->toBe('2026-08-25 09:00');
});

it('never suggests a Sunday slot', function () {
    for ($i = 1; $i <= 15; $i++) {
        callAt(9, $i, CallOutcome::Connected);
    }

    // A no-answer late Saturday — the only 9 AM slot within reach is Sunday, so it must skip to Monday.
    $notBefore = Carbon::parse('2026-08-22 23:00:00', 'Asia/Kolkata'); // a Saturday
    $slot = app(CallTimingMetrics::class)->suggestNextCallSlot($notBefore);

    expect($slot->dayOfWeek)->not->toBe(Carbon::SUNDAY)
        ->and($slot->timezone('Asia/Kolkata')->format('Y-m-d H:i'))->toBe('2026-08-24 09:00');
});

it('returns null from suggestNextCallSlot() when there is no qualifying data', function () {
    expect(app(CallTimingMetrics::class)->suggestNextCallSlot(now()))->toBeNull();
});
