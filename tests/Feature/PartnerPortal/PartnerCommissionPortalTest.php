<?php

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Partner;
use App\Support\Money;

it('shows the partner their own live commission estimate and history', function () {
    $partner = Partner::factory()->portalUser()->create(['commission_rate' => 10]);
    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 50_000 * 100,
    ]);

    $this->actingAs($partner, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertSee('Your Earnings')
        ->assertSee('5,000'); // 10% of ₹50,000
});

it('hides the earnings section entirely when the partner has no commission rate set', function () {
    $partner = Partner::factory()->portalUser()->create(['commission_rate' => null]);

    $this->actingAs($partner, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertDontSee('Your Earnings');
});

it('does not show another partner\'s commission on this partner\'s dashboard', function () {
    $partner = Partner::factory()->portalUser()->create(['commission_rate' => 10]);
    $otherPartner = Partner::factory()->portalUser()->create(['commission_rate' => 10]);

    Deal::factory()->create([
        'partner_id' => $otherPartner->id,
        'stage' => DealStage::Won,
        'won_at' => now(),
        'value' => 900_000 * 100,
    ]);

    $this->actingAs($partner, 'partner')
        ->get(route('partner-portal.home'))
        ->assertOk()
        ->assertDontSee(Money::format(90_000 * 100)); // otherPartner's commission amount
});
