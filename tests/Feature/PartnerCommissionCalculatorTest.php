<?php

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Partner;
use App\Services\PartnerCommissionCalculator;
use Illuminate\Support\Carbon;

function partnerCommissionCalc(): PartnerCommissionCalculator
{
    return app(PartnerCommissionCalculator::class);
}

it('computes a flat-rate commission on referred deals won within the month', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(5),
        'value' => 100_000 * 100,
    ]);

    $estimate = partnerCommissionCalc()->estimateForPartner($partner, $monthStart);

    expect($estimate['referred_value'])->toBe(100_000 * 100)
        ->and($estimate['commission_rate'])->toBe(10.0)
        ->and($estimate['commission_amount'])->toBe(10_000 * 100);
});

it('ignores deals outside the target month and deals not owned by this partner', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $otherPartner = Partner::factory()->create(['commission_rate' => 10]);
    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->subDay(), // May, not June
        'value' => 100_000 * 100,
    ]);

    Deal::factory()->create([
        'partner_id' => $otherPartner->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(3),
        'value' => 100_000 * 100,
    ]);

    expect(partnerCommissionCalc()->monthlyReferralsForPartner($partner, $monthStart))->toBe(0);
});

it('ignores a deal that is not yet Won', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Proposal,
        'value' => 100_000 * 100,
    ]);

    expect(partnerCommissionCalc()->monthlyReferralsForPartner($partner, $monthStart))->toBe(0);
});

it('returns a zero-amount estimate, not null, when the partner has no commission rate', function () {
    $partner = Partner::factory()->create(['commission_rate' => null]);
    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDay(),
        'value' => 100_000 * 100,
    ]);

    $estimate = partnerCommissionCalc()->estimateForPartner($partner, $monthStart);

    expect($estimate['commission_rate'])->toBe(0.0)
        ->and($estimate['commission_amount'])->toBe(0);
});
