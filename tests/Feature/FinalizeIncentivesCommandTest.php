<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\IncentiveStatement;
use App\Models\User;
use Illuminate\Support\Carbon;

it('creates one locked statement per active Sales user for the target month', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();
    $inactiveRep = User::factory()->role(UserRole::Sales)->create(['is_active' => false]);

    $monthStart = Carbon::create(2026, 6, 1);

    Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => $monthStart->copy()->addDays(5),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-incentives', ['--month' => '2026-06'])->assertSuccessful();

    $statement = IncentiveStatement::where('user_id', $rep->id)->whereDate('period_start', '2026-06-01')->first();

    expect($statement)->not->toBeNull()
        ->and($statement->sales_value)->toBe(60_000 * 100)
        ->and($statement->finalized_at)->not->toBeNull();

    expect(IncentiveStatement::where('user_id', $inactiveRep->id)->exists())->toBeFalse();
});

it('re-running for the same month updates the existing statement instead of duplicating', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();

    Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 10),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-incentives', ['--month' => '2026-06'])->assertSuccessful();

    // A late-added Won deal in the same month, then a second run — should
    // update the same row, not create a second one.
    Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 15),
        'value' => 40_000 * 100,
    ]);

    $this->artisan('app:finalize-incentives', ['--month' => '2026-06'])->assertSuccessful();

    expect(IncentiveStatement::where('user_id', $rep->id)->whereDate('period_start', '2026-06-01')->count())->toBe(1);

    $statement = IncentiveStatement::where('user_id', $rep->id)->whereDate('period_start', '2026-06-01')->first();
    expect($statement->sales_value)->toBe(100_000 * 100);
});

it('does not change a locked statement when a Deal is edited after finalization', function () {
    $rep = User::factory()->role(UserRole::Sales)->create();

    $deal = Deal::factory()->create([
        'owner_id' => $rep->id,
        'stage' => DealStage::Won,
        'won_at' => Carbon::create(2026, 6, 10),
        'value' => 60_000 * 100,
    ]);

    $this->artisan('app:finalize-incentives', ['--month' => '2026-06'])->assertSuccessful();

    $before = IncentiveStatement::where('user_id', $rep->id)->whereDate('period_start', '2026-06-01')->first();

    // Edit the deal's value after the month was already finalized.
    $deal->update(['value' => 900_000 * 100]);

    $after = IncentiveStatement::where('user_id', $rep->id)->whereDate('period_start', '2026-06-01')->first();

    expect($after->sales_value)->toBe($before->sales_value)
        ->and($after->updated_at->equalTo($before->updated_at))->toBeTrue();
});
