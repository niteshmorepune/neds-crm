<?php

use App\Enums\DealStage;
use App\Enums\TargetPeriodType;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\IncentiveSetting;
use App\Models\SalesTarget;
use App\Models\User;
use App\Services\IncentiveCalculator;

function incentiveCalc(): IncentiveCalculator
{
    return app(IncentiveCalculator::class);
}

it('applies the 6% rate to sales within the first slab', function () {
    // ₹40,000 sales → 6% of the whole amount (well within the first band).
    expect(incentiveCalc()->slabIncentive(40_000 * 100))->toBe((int) round(40_000 * 100 * 0.06));
});

it('applies exactly 6% at the ₹50,000 boundary', function () {
    expect(incentiveCalc()->slabIncentive(50_000 * 100))->toBe((int) round(50_000 * 100 * 0.06));
});

it('applies marginal (bracket) rates, not a cliff, just above a boundary', function () {
    // ₹50,001: ₹50,000 at 6% + ₹1 at 10% — must NOT be 10% of the whole ₹50,001.
    $calc = incentiveCalc();
    $salesValue = 50_001 * 100;

    $expected = (int) round(50_000 * 100 * 0.06 + 1 * 100 * 0.10);
    $cliffWouldBe = (int) round($salesValue * 0.10);

    expect($calc->slabIncentive($salesValue))
        ->toBe($expected)
        ->not->toBe($cliffWouldBe);
});

it('computes the correct marginal total across all five brackets', function () {
    // ₹3,00,000: 50k@6% + 50k@10% + 50k@12.5% + 1,00,000@15% + 50,000@20%
    $salesValue = 300_000 * 100;
    $expected = (int) round(
        50_000 * 100 * 0.06
        + 50_000 * 100 * 0.10
        + 50_000 * 100 * 0.125
        + 100_000 * 100 * 0.15
        + 50_000 * 100 * 0.20
    );

    expect(incentiveCalc()->slabIncentive($salesValue))->toBe($expected);
});

it('returns zero incentive for zero sales', function () {
    expect(incentiveCalc()->slabIncentive(0))->toBe(0);
});

it('reports the current and next slab correctly', function () {
    $calc = incentiveCalc();

    $current = $calc->currentSlab(75_000 * 100);
    expect($current['rate'])->toBe(10.0);

    $next = $calc->nextSlab(75_000 * 100);
    expect($next['rate'])->toBe(12.5);

    expect($calc->nextSlab(300_000 * 100))->toBeNull();
});

it('detects the company target as met only when Won value reaches it', function () {
    $monthStart = TargetPeriodType::Month->currentPeriodStart();
    SalesTarget::factory()->create([
        'user_id' => null,
        'period_type' => TargetPeriodType::Month,
        'period_start' => $monthStart,
        'target_value' => 500_000 * 100,
    ]);

    Deal::factory()->create([
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(2),
        'value' => 400_000 * 100,
    ]);

    expect(incentiveCalc()->companyTargetMet($monthStart))->toBeFalse();

    Deal::factory()->create([
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(3),
        'value' => 200_000 * 100,
    ]);

    expect(incentiveCalc()->companyTargetMet($monthStart))->toBeTrue();
});

it('splits the team bonus pool evenly across active Sales users only when target is met', function () {
    $monthStart = TargetPeriodType::Month->currentPeriodStart();
    IncentiveSetting::current()->update(['team_bonus_pool' => 10_000 * 100]);

    User::factory()->count(4)->role(UserRole::Sales)->create();
    User::factory()->role(UserRole::Support)->create(); // not eligible

    SalesTarget::factory()->create([
        'user_id' => null,
        'period_type' => TargetPeriodType::Month,
        'period_start' => $monthStart,
        'target_value' => 100 * 100,
    ]);

    // No Won deals yet — target not met.
    expect(incentiveCalc()->teamBonusShare($monthStart))->toBe(0);

    Deal::factory()->create([
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDay(),
        'value' => 500 * 100,
    ]);

    expect(incentiveCalc()->teamBonusShare($monthStart))->toBe((int) intdiv(10_000 * 100, 4));
});
