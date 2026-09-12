<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\OfferKey;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Support\Facades\Artisan;

it('corrects a lead\'s habitual GMB tag to match its own resolved recommendation', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $website = Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'service_id' => $gmb->id,
        'goal' => LeadGoal::GrowBusiness,
        'budget_range' => LeadBudgetRange::ThreeToSix,
        'recommendation_offer_key' => OfferKey::WebsiteGrowthAudit->value,
    ]);

    Artisan::call('app:backfill-lead-service-tags');

    expect($lead->fresh()->service_id)->toBe($website->id);
});

it('leaves a lead whose service_id already matches its recommendation untouched', function () {
    $website = Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'service_id' => $website->id,
        'recommendation_offer_key' => OfferKey::WebsiteGrowthAudit->value,
    ]);

    Artisan::call('app:backfill-lead-service-tags');

    expect($lead->fresh()->service_id)->toBe($website->id);
});

it('leaves a GrowthStrategy-recommended lead\'s service_id untouched, since that offer has no 1:1 mapping', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'service_id' => $gmb->id,
        'recommendation_offer_key' => OfferKey::GrowthStrategy->value,
    ]);

    Artisan::call('app:backfill-lead-service-tags');

    expect($lead->fresh()->service_id)->toBe($gmb->id);
});

it('skips a lead with no resolved recommendation at all', function () {
    $lead = Lead::factory()->create(['service_id' => null, 'recommendation_offer_key' => null]);

    Artisan::call('app:backfill-lead-service-tags');

    expect($lead->fresh()->service_id)->toBeNull();
});

it('changes nothing in dry-run mode', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    Service::factory()->create(['name' => 'Website Design & Development', 'is_active' => true]);

    $lead = Lead::factory()->create([
        'service_id' => $gmb->id,
        'recommendation_offer_key' => OfferKey::WebsiteGrowthAudit->value,
    ]);

    Artisan::call('app:backfill-lead-service-tags', ['--dry-run' => true]);

    expect($lead->fresh()->service_id)->toBe($gmb->id);
});
