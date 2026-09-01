<?php

use App\Enums\DealLostReason;
use App\Enums\DealStage;
use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use App\Services\LossReasonMetrics;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(LossReasonMetrics::class);
});

/** Creates an already-Lost deal with a specific stage_changed_at, bypassing moveToStage() like the rest of this app's tests do (see Deal::moveToStage()'s own docblock). */
function lostDeal(array $attributes, ?Carbon $lostAt = null): Deal
{
    $deal = Deal::factory()->stage(DealStage::Lost)->create($attributes + [
        'lost_reason' => DealLostReason::Price,
    ]);

    if ($lostAt !== null) {
        $deal->forceFill(['stage_changed_at' => $lostAt])->saveQuietly();
    }

    return $deal;
}

it('breaks down lost deals by reason overall, with count/pct/value', function () {
    lostDeal(['lost_reason' => DealLostReason::Price, 'value' => 100000]);
    lostDeal(['lost_reason' => DealLostReason::Price, 'value' => 200000]);
    lostDeal(['lost_reason' => DealLostReason::Competitor, 'value' => 100000]);
    lostDeal(['lost_reason' => DealLostReason::WentDark, 'value' => 100000]);

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(4);
    $price = collect($data['overall'])->firstWhere('reason', 'price');
    expect($price['count'])->toBe(2)
        ->and($price['pct'])->toBe(50)
        ->and($price['value'])->toBe(300000);

    $competitor = collect($data['overall'])->firstWhere('reason', 'competitor');
    expect($competitor['count'])->toBe(1)->and($competitor['pct'])->toBe(25);
});

it('excludes deals lost outside the requested date range', function () {
    lostDeal(['lost_reason' => DealLostReason::Price], lostAt: now()->subMonths(3));
    lostDeal(['lost_reason' => DealLostReason::Price], lostAt: now());

    $data = $this->metrics->lossReasonBreakdown(now()->startOfMonth(), now()->endOfMonth());

    expect($data['total'])->toBe(1);
});

it('excludes deals that are not in the Lost stage even if lost_reason happens to be set', function () {
    Deal::factory()->stage(DealStage::Negotiation)->create(['lost_reason' => null]);

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(0);
});

it('groups by rep, falling back to Unassigned when the deal has no owner', function () {
    $rep = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    lostDeal(['owner_id' => $rep->id, 'lost_reason' => DealLostReason::Price]);
    lostDeal(['owner_id' => $rep->id, 'lost_reason' => DealLostReason::Competitor]);
    lostDeal(['owner_id' => null, 'lost_reason' => DealLostReason::Timing]);

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    $kiranGroup = collect($data['by_rep'])->firstWhere('label', 'Kiran Katte');
    expect($kiranGroup['total'])->toBe(2);
    $kiranPrice = collect($kiranGroup['by_reason'])->firstWhere('reason', 'price');
    expect($kiranPrice['count'])->toBe(1)->and($kiranPrice['pct'])->toBe(50);

    $unassigned = collect($data['by_rep'])->firstWhere('label', 'Unassigned');
    expect($unassigned['total'])->toBe(1);
});

it('groups by lead source, falling back to Direct (no lead) when the deal never came from a Lead', function () {
    $lead = Lead::factory()->create(['source' => LeadSource::MetaAds]);
    lostDeal(['lead_id' => $lead->id, 'lost_reason' => DealLostReason::Price]);
    lostDeal(['lead_id' => null, 'lost_reason' => DealLostReason::Timing]);

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    $metaGroup = collect($data['by_source'])->firstWhere('label', 'Meta Ads');
    expect($metaGroup['total'])->toBe(1);

    $direct = collect($data['by_source'])->firstWhere('label', 'Direct (no lead)');
    expect($direct['total'])->toBe(1);
});

it('groups by score band using the lead\'s ai_score, with No score data for a deal with no lead or no score', function () {
    $hotLead = Lead::factory()->create(['ai_score' => 85]);
    $coldLead = Lead::factory()->create(['ai_score' => 10]);
    lostDeal(['lead_id' => $hotLead->id, 'lost_reason' => DealLostReason::Competitor]);
    lostDeal(['lead_id' => $coldLead->id, 'lost_reason' => DealLostReason::Price]);
    lostDeal(['lead_id' => null, 'lost_reason' => DealLostReason::Timing]);

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    expect(collect($data['by_score_band'])->firstWhere('label', 'Hot')['total'])->toBe(1);
    expect(collect($data['by_score_band'])->firstWhere('label', 'Cold')['total'])->toBe(1);
    expect(collect($data['by_score_band'])->firstWhere('label', 'No score data')['total'])->toBe(1);
});

it('computes AI suggestion calibration stats: accepted, overridden, no_suggestion', function () {
    lostDeal(['lost_reason' => DealLostReason::Price, 'ai_suggested_lost_reason' => DealLostReason::Price]); // accepted
    lostDeal(['lost_reason' => DealLostReason::Competitor, 'ai_suggested_lost_reason' => DealLostReason::Price]); // overridden
    lostDeal(['lost_reason' => DealLostReason::Timing, 'ai_suggested_lost_reason' => null]); // no suggestion
    lostDeal(['lost_reason' => DealLostReason::Timing, 'ai_suggested_lost_reason' => null]); // no suggestion

    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    expect($data['ai_suggestion_stats'])->toBe([
        'accepted' => 1,
        'overridden' => 1,
        'no_suggestion' => 2,
        'accepted_pct' => 25,
    ]);
});

it('handles zero deals lost in the period without crashing, all counts zero', function () {
    $data = $this->metrics->lossReasonBreakdown(now()->subMonth(), now()->addMonth());

    expect($data['total'])->toBe(0)
        ->and($data['by_rep'])->toBe([])
        ->and($data['by_source'])->toBe([])
        ->and($data['by_score_band'])->toBe([])
        ->and($data['ai_suggestion_stats']['accepted_pct'])->toBe(0);
    foreach ($data['overall'] as $r) {
        expect($r['count'])->toBe(0)->and($r['pct'])->toBe(0);
    }
});

describe('Deal::aiSuggestionOutcome()', function () {
    it('is null for a deal that is not Lost', function () {
        $deal = Deal::factory()->stage(DealStage::Negotiation)->create();
        expect($deal->aiSuggestionOutcome())->toBeNull();
    });

    it('is no_suggestion when no AI suggestion was ever persisted', function () {
        $deal = lostDeal(['lost_reason' => DealLostReason::Price, 'ai_suggested_lost_reason' => null]);
        expect($deal->aiSuggestionOutcome())->toBe('no_suggestion');
    });

    it('is accepted when the final reason matches the AI suggestion', function () {
        $deal = lostDeal(['lost_reason' => DealLostReason::Competitor, 'ai_suggested_lost_reason' => DealLostReason::Competitor]);
        expect($deal->aiSuggestionOutcome())->toBe('accepted');
    });

    it('is overridden when the rep picked something other than the AI suggestion', function () {
        $deal = lostDeal(['lost_reason' => DealLostReason::Price, 'ai_suggested_lost_reason' => DealLostReason::Competitor]);
        expect($deal->aiSuggestionOutcome())->toBe('overridden');
    });
});

describe('reports/loss-reasons route', function () {
    it('is reachable by Admin and Manager', function () {
        lostDeal(['lost_reason' => DealLostReason::Price]);

        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->get(route('reports.loss-reasons'))->assertOk()->assertSee('Loss Reasons');

        $manager = User::factory()->role(UserRole::Manager)->create();
        $this->actingAs($manager)->get(route('reports.loss-reasons'))->assertOk();
    });

    it('is forbidden for Sales', function () {
        $sales = User::factory()->role(UserRole::Sales)->create();
        $this->actingAs($sales)->get(route('reports.loss-reasons'))->assertForbidden();
    });

    it('exports a CSV with the expected sections', function () {
        lostDeal(['lost_reason' => DealLostReason::Price]);
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.loss-reasons.export'));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        expect($csv)->toContain('Overall distribution')
            ->toContain('Loss reasons by rep')
            ->toContain('Loss reasons by lead source')
            ->toContain('Loss reasons by score band')
            ->toContain('AI suggestion calibration');
    });

    it('respects the month filter', function () {
        lostDeal(['lost_reason' => DealLostReason::Price], lostAt: now()->subMonths(2));
        $admin = User::factory()->role(UserRole::Admin)->create();

        $response = $this->actingAs($admin)->get(route('reports.loss-reasons', ['month' => now()->format('Y-m')]));

        $response->assertOk()->assertSee('No deals lost in this period.');
    });
});
