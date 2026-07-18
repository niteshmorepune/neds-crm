<?php

use App\Enums\DealStage;
use App\Enums\LeadSource;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Service;
use App\Services\SimilarDealFinder;

beforeEach(function () {
    $this->finder = app(SimilarDealFinder::class);
    $this->serviceA = Service::factory()->create(['name' => 'Website Design & Development']);
    $this->serviceB = Service::factory()->create(['name' => 'SEO']);
});

it('excludes deals for a different service', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);
    Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceB->id, 'value' => 150000]);

    expect($this->finder->find($deal))->toBeEmpty();
});

it('excludes deals that are still open (not Won or Lost)', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);
    Deal::factory()->stage(DealStage::Negotiation)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);

    expect($this->finder->find($deal))->toBeEmpty();
});

it('excludes the deal itself even though it may already be closed', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);

    expect($this->finder->find($deal))->toBeEmpty();
});

it('returns no candidates when the deal itself has no service', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => null, 'value' => 150000]);
    Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);

    expect($this->finder->find($deal))->toBeEmpty();
});

it('includes both Won and Lost deals in the candidate pool', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);
    $won = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 140000]);
    $lost = Deal::factory()->stage(DealStage::Lost)->create(['service_id' => $this->serviceA->id, 'value' => 160000]);

    $result = $this->finder->find($deal);

    expect($result->pluck('id')->sort()->values()->all())->toBe(collect([$won->id, $lost->id])->sort()->values()->all());
});

it('ranks candidates by closest value first, ignoring source when values differ', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);

    $far = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 90000]); // diff 60,000
    $mid = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 180000]); // diff 30,000
    $close = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 140000]); // diff 10,000

    $result = $this->finder->find($deal);

    expect($result->pluck('id')->all())->toBe([$close->id, $mid->id, $far->id]);
});

it('uses a matching lead source only as a tiebreak when value diff is equal', function () {
    $lead = Lead::factory()->create(['source' => LeadSource::Referral]);
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000, 'lead_id' => $lead->id]);

    $sameSourceLead = Lead::factory()->create(['source' => LeadSource::Referral]);
    $matchingSource = Deal::factory()->stage(DealStage::Won)->create([
        'service_id' => $this->serviceA->id, 'value' => 140000, 'lead_id' => $sameSourceLead->id,
    ]);

    $otherSourceLead = Lead::factory()->create(['source' => LeadSource::Website]);
    $differentSource = Deal::factory()->stage(DealStage::Won)->create([
        'service_id' => $this->serviceA->id, 'value' => 160000, 'lead_id' => $otherSourceLead->id,
    ]);

    // Both candidates are exactly 10,000 paise off — same value diff — so the
    // matching source (Referral) must win the tie and rank first.
    $result = $this->finder->find($deal);

    expect($result->pluck('id')->all())->toBe([$matchingSource->id, $differentSource->id])
        ->and($result->first()->source_matches)->toBeTrue()
        ->and($result->last()->source_matches)->toBeFalse();
});

it('never lets a lead-less deal treat "no source" as a match against another lead-less deal', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000, 'lead_id' => null]);
    $candidate = Deal::factory()->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 150000, 'lead_id' => null]);

    $result = $this->finder->find($deal);

    expect($result->first()->id)->toBe($candidate->id)
        ->and($result->first()->source_matches)->toBeFalse();
});

it('limits results to the given limit', function () {
    $deal = Deal::factory()->stage(DealStage::Contacted)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);
    Deal::factory()->count(5)->stage(DealStage::Won)->create(['service_id' => $this->serviceA->id, 'value' => 150000]);

    expect($this->finder->find($deal, 3))->toHaveCount(3);
});
