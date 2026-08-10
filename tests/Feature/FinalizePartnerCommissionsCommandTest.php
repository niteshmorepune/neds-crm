<?php

use App\Enums\DealStage;
use App\Models\Deal;
use App\Models\Partner;
use App\Models\PartnerCommissionStatement;
use Illuminate\Support\Carbon;

it('creates one locked statement per commission-eligible partner for the target month', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);
    $noRatePartner = Partner::factory()->create(['commission_rate' => null]);

    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(5),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-partner-commissions', ['--month' => '2026-06'])->assertSuccessful();

    $statement = PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->first();

    expect($statement)->not->toBeNull()
        ->and($statement->referred_value)->toBe(60_000 * 100)
        ->and($statement->commission_amount)->toBe(6_000 * 100)
        ->and($statement->finalized_at)->not->toBeNull();

    expect(PartnerCommissionStatement::where('partner_id', $noRatePartner->id)->exists())->toBeFalse();
});

it('re-running for the same month updates the existing statement instead of duplicating', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 10),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-partner-commissions', ['--month' => '2026-06'])->assertSuccessful();

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 15),
        'value' => 40_000 * 100,
    ]);

    $this->artisan('app:finalize-partner-commissions', ['--month' => '2026-06'])->assertSuccessful();

    expect(PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->count())->toBe(1);

    $statement = PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->first();
    expect($statement->referred_value)->toBe(100_000 * 100);
});

it('does not change a locked statement when a Deal is edited after finalization', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);

    $deal = Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 10),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-partner-commissions', ['--month' => '2026-06'])->assertSuccessful();

    $before = PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->first();

    $deal->update(['value' => 900_000 * 100]);

    $after = PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->first();

    expect($after->referred_value)->toBe($before->referred_value)
        ->and($after->updated_at->equalTo($before->updated_at))->toBeTrue();
});

it('snapshots the commission rate at finalize time so a later rate change does not alter a locked statement', function () {
    $partner = Partner::factory()->create(['commission_rate' => 10]);

    Deal::factory()->create([
        'partner_id' => $partner->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 10),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-partner-commissions', ['--month' => '2026-06'])->assertSuccessful();

    $partner->update(['commission_rate' => 20]);

    $statement = PartnerCommissionStatement::where('partner_id', $partner->id)->whereDate('period_start', '2026-06-01')->first();

    expect((float) $statement->commission_rate)->toBe(10.0)
        ->and($statement->commission_amount)->toBe(6_000 * 100);
});
