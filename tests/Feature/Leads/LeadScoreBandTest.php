<?php

use App\Models\Lead;

it('bands scores into cold, warm, hot at the existing badge thresholds', function () {
    expect(Lead::scoreBandFor(0))->toBe('cold');
    expect(Lead::scoreBandFor(39))->toBe('cold');
    expect(Lead::scoreBandFor(40))->toBe('warm');
    expect(Lead::scoreBandFor(69))->toBe('warm');
    expect(Lead::scoreBandFor(70))->toBe('hot');
    expect(Lead::scoreBandFor(100))->toBe('hot');
    expect(Lead::scoreBandFor(null))->toBeNull();
});

it('follows the configurable hot_lead_threshold, not a hardcoded 70', function () {
    config(['services.anthropic.hot_lead_threshold' => 60]);

    expect(Lead::scoreBandFor(65))->toBe('hot')
        ->and(Lead::scoreBandFor(55))->toBe('warm');
});

it('labels each band, with a fallback for no score data', function () {
    expect(Lead::scoreBandLabel('hot'))->toBe('Hot')
        ->and(Lead::scoreBandLabel('warm'))->toBe('Warm')
        ->and(Lead::scoreBandLabel('cold'))->toBe('Cold')
        ->and(Lead::scoreBandLabel(null))->toBe('No score data')
        ->and(Lead::scoreBandLabel('no_score'))->toBe('No score data');
});

it('still renders the same badge colors on the lead-score component after the scoreBandFor refactor', function () {
    $hot = Lead::factory()->create(['ai_score' => 85]);
    $warm = Lead::factory()->create(['ai_score' => 50]);
    $cold = Lead::factory()->create(['ai_score' => 10]);

    expect(view('components.lead-score', ['lead' => $hot])->render())->toContain('bg-green-100');
    expect(view('components.lead-score', ['lead' => $warm])->render())->toContain('bg-yellow-100');
    expect(view('components.lead-score', ['lead' => $cold])->render())->toContain('bg-gray-100');
});
