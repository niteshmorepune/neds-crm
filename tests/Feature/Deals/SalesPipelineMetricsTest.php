<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\DealStageTransition;
use App\Models\User;
use App\Services\SalesPipelineMetrics;
use Illuminate\Support\Carbon;

/**
 * Builds a deal whose FIRST transition (auto-created by Deal::booted() on
 * factory creation) is backdated to $start, then appends the rest of
 * $stageDays as controlled-timestamp transitions — so the resulting dwell
 * time in each stage is exactly the number of days given, independent of
 * real elapsed time.
 *
 * @param  list<array{0: DealStage, 1: int}>  $stageDays  [stage, days spent in that stage before the next transition]
 */
function dealWithStageHistory(?int $ownerId, array $stageDays, Carbon $start): Deal
{
    [$firstStage] = $stageDays[0];
    $deal = Deal::factory()->create(['stage' => $firstStage, 'owner_id' => $ownerId]);

    $cursor = $start->copy();
    $firstTransition = DealStageTransition::where('deal_id', $deal->id)->firstOrFail();
    $firstTransition->forceFill(['created_at' => $cursor])->saveQuietly();

    for ($i = 0; $i < count($stageDays); $i++) {
        [$stage, $days] = $stageDays[$i];
        $cursor = $cursor->copy()->addDays($days);

        $next = $stageDays[$i + 1][0] ?? null;
        if ($next === null) {
            break; // last entry's "days" has no following transition to close its dwell period
        }

        $t = DealStageTransition::create(['deal_id' => $deal->id, 'from_stage' => $stage->value, 'to_stage' => $next->value]);
        $t->forceFill(['created_at' => $cursor])->saveQuietly();
    }

    return $deal;
}

beforeEach(function () {
    $this->metrics = app(SalesPipelineMetrics::class);
    $this->repA = User::factory()->role(UserRole::Sales)->create(['name' => 'Rep A']);
    $this->repB = User::factory()->role(UserRole::Sales)->create(['name' => 'Rep B']);
});

it('computes a rep\'s average dwell time in a stage against the team average', function () {
    $start = now()->subDays(60);

    // Rep A: 3 completed dwell periods in Contacted — 5, 6, 7 days (avg 6).
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 5], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 6], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 7], [DealStage::Negotiation, 0]], $start);

    // Rep B: 3 completed dwell periods in Contacted — 1, 2, 3 days (avg 2).
    dealWithStageHistory($this->repB->id, [[DealStage::New, 0], [DealStage::Contacted, 1], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repB->id, [[DealStage::New, 0], [DealStage::Contacted, 2], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repB->id, [[DealStage::New, 0], [DealStage::Contacted, 3], [DealStage::Negotiation, 0]], $start);

    $result = $this->metrics->repStageDwellTimes();

    // Team average across all 6 Contacted dwells: (5+6+7+1+2+3)/6 = 4.0.
    expect($result[$this->repA->id]['contacted'])->toBe([
        'rep_avg_days' => 6.0,
        'rep_sample' => 3,
        'team_avg_days' => 4.0,
        'team_sample' => 6,
    ]);
    expect($result[$this->repB->id]['contacted'])->toBe([
        'rep_avg_days' => 2.0,
        'rep_sample' => 3,
        'team_avg_days' => 4.0,
        'team_sample' => 6,
    ]);
});

it('omits a rep+stage combo below the minimum dwell sample', function () {
    $start = now()->subDays(30);

    // Only 2 completed dwell periods — below MIN_DWELL_SAMPLE (3).
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 5], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 6], [DealStage::Negotiation, 0]], $start);

    $result = $this->metrics->repStageDwellTimes();

    expect($result[$this->repA->id] ?? [])->not->toHaveKey('contacted');
});

it('never attributes dwell time in a terminal stage (Won/Lost)', function () {
    $start = now()->subDays(30);

    // 3 deals that reach Won and (per the append-only transition log) never
    // move again — Won itself should never appear as a dwell-time stage.
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Negotiation, 3], [DealStage::Won, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Negotiation, 4], [DealStage::Won, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Negotiation, 5], [DealStage::Won, 0]], $start);

    $result = $this->metrics->repStageDwellTimes();

    expect($result[$this->repA->id])->toHaveKey('negotiation')
        ->and($result[$this->repA->id])->not->toHaveKey('won');
});

it('excludes deals with no owner from the rep breakdown but still counts them toward the team average', function () {
    $start = now()->subDays(30);

    dealWithStageHistory(null, [[DealStage::New, 0], [DealStage::Contacted, 10], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 2], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 3], [DealStage::Negotiation, 0]], $start);
    dealWithStageHistory($this->repA->id, [[DealStage::New, 0], [DealStage::Contacted, 4], [DealStage::Negotiation, 0]], $start);

    $result = $this->metrics->repStageDwellTimes();

    // Team average includes the unowned deal's 10-day dwell: (10+2+3+4)/4 = 4.75.
    expect($result[$this->repA->id]['contacted']['team_avg_days'])->toBe(4.8)
        ->and($result[$this->repA->id]['contacted']['team_sample'])->toBe(4)
        ->and($result[$this->repA->id]['contacted']['rep_avg_days'])->toBe(3.0);
});
