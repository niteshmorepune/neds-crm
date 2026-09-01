<?php

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\ScoreCalibrationSnapshot;
use App\Services\ScoreCalibrationMetrics;
use Illuminate\Support\Carbon;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-08-01 07:30:00'));
    $this->metrics = app(ScoreCalibrationMetrics::class);
});

afterEach(function () {
    Carbon::setTestNow();
});

it('snapshots the month that just ended, matching the on-demand report for the same period', function () {
    $lead = Lead::factory()->create(['ai_score' => 85, 'status' => LeadStatus::New]);
    $lead->update(['status' => LeadStatus::Converted, 'converted_at' => Carbon::parse('2026-07-15')]);

    $this->artisan('app:snapshot-score-calibration')->assertSuccessful();

    $liveData = $this->metrics->scoreCalibration(Carbon::parse('2026-07-01')->startOfMonth(), Carbon::parse('2026-07-01')->endOfMonth());
    $hotBucket = collect($liveData['buckets'])->firstWhere('band', 'hot');

    $snapshot = ScoreCalibrationSnapshot::where('band', 'hot')->whereDate('period_start', '2026-07-01')->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->total)->toBe($hotBucket['total'])
        ->and($snapshot->converted)->toBe($hotBucket['converted'])
        ->and($snapshot->conversion_rate)->toBe($hotBucket['conversion_rate']);
});

it('writes one snapshot row per band', function () {
    $this->artisan('app:snapshot-score-calibration')->assertSuccessful();

    expect(ScoreCalibrationSnapshot::whereDate('period_start', '2026-07-01')->count())->toBe(4);
});

it('is idempotent: running twice for the same period updates rather than duplicates', function () {
    $lead = Lead::factory()->create(['ai_score' => 85, 'status' => LeadStatus::New]);
    $lead->update(['status' => LeadStatus::Converted, 'converted_at' => Carbon::parse('2026-07-15')]);

    $this->artisan('app:snapshot-score-calibration')->assertSuccessful();
    expect(ScoreCalibrationSnapshot::where('band', 'hot')->whereDate('period_start', '2026-07-01')->count())->toBe(1);

    // Another Hot lead closes after the first snapshot -- re-running should
    // pick up the new total, not sit stale or add a second row.
    $lead2 = Lead::factory()->create(['ai_score' => 90, 'status' => LeadStatus::New]);
    $lead2->update(['status' => LeadStatus::Converted, 'converted_at' => Carbon::parse('2026-07-20')]);

    $this->artisan('app:snapshot-score-calibration')->assertSuccessful();

    $rows = ScoreCalibrationSnapshot::where('band', 'hot')->whereDate('period_start', '2026-07-01')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows->first()->total)->toBe(2);
});

it('respects a custom --month option', function () {
    $lead = Lead::factory()->create(['ai_score' => 20, 'status' => LeadStatus::New]);
    $lead->update(['status' => LeadStatus::Lost]);
    $lead->forceFill(['lost_at' => Carbon::parse('2026-05-10')])->saveQuietly();

    $this->artisan('app:snapshot-score-calibration', ['--month' => '2026-05'])->assertSuccessful();

    $snapshot = ScoreCalibrationSnapshot::where('band', 'cold')->whereDate('period_start', '2026-05-01')->first();
    expect($snapshot)->not->toBeNull()->and($snapshot->total)->toBe(1);
    // The default month-just-ended (July) should have nothing.
    expect(ScoreCalibrationSnapshot::whereDate('period_start', '2026-07-01')->count())->toBe(0);
});

it('is a no-op when disabled via config', function () {
    config(['services.reports.score_calibration_snapshot_enabled' => false]);

    $this->artisan('app:snapshot-score-calibration')->assertSuccessful();

    expect(ScoreCalibrationSnapshot::count())->toBe(0);
});

describe('ScoreCalibrationMetrics::trend()', function () {
    it('orders periods oldest first and shows each band conversion rate per period', function () {
        ScoreCalibrationSnapshot::create(['period_start' => '2026-06-01', 'band' => 'hot', 'total' => 2, 'converted' => 2, 'lost' => 0, 'conversion_rate' => 100]);
        ScoreCalibrationSnapshot::create(['period_start' => '2026-06-01', 'band' => 'cold', 'total' => 4, 'converted' => 1, 'lost' => 3, 'conversion_rate' => 25]);
        ScoreCalibrationSnapshot::create(['period_start' => '2026-07-01', 'band' => 'hot', 'total' => 3, 'converted' => 2, 'lost' => 1, 'conversion_rate' => 67]);

        $trend = $this->metrics->trend();

        expect($trend)->toHaveCount(2)
            ->and($trend[0]['period'])->toBe('2026-06')
            ->and($trend[0]['hot'])->toBe(100)
            ->and($trend[0]['cold'])->toBe(25)
            ->and($trend[0]['warm'])->toBeNull()
            ->and($trend[1]['period'])->toBe('2026-07')
            ->and($trend[1]['hot'])->toBe(67);
    });

    it('returns an empty list when no snapshots exist yet', function () {
        expect($this->metrics->trend())->toBe([]);
    });

    it('limits to the last $months periods', function () {
        foreach (range(1, 15) as $i) {
            ScoreCalibrationSnapshot::create([
                'period_start' => Carbon::parse('2025-01-01')->addMonths($i - 1),
                'band' => 'hot', 'total' => 1, 'converted' => 1, 'lost' => 0, 'conversion_rate' => 100,
            ]);
        }

        $trend = $this->metrics->trend(months: 12);

        expect($trend)->toHaveCount(12);
    });
});
