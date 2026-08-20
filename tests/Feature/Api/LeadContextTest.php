<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\VisibilityAuditFunnelEvent;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.whatsapp_webhook.token' => 'test-wa-token']);
    // LeadObserver dispatches several jobs on every eligible Lead::factory()
    // create below (ScoreLead, SendVisibilityAuditFirstInviteJob, ...) —
    // fake the queue so this file stays scoped to the endpoint's own response.
    Queue::fake();
});

it('rejects a request with no bearer token', function () {
    $this->getJson('/api/leads/context?phone=919876543210')->assertUnauthorized();
});

it('rejects a request with the wrong bearer token', function () {
    $this->getJson('/api/leads/context?phone=919876543210', ['Authorization' => 'Bearer wrong'])
        ->assertUnauthorized();
});

it('returns found=false when no lead matches the phone', function () {
    $this->getJson('/api/leads/context?phone=919876543210', ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['found' => false]);
});

it('returns found=false when no phone is given', function () {
    $this->getJson('/api/leads/context', ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['found' => false]);
});

it('returns full context for a matched lead, splitting the budget answer out of the rest', function () {
    $gmb = Service::factory()->create(['name' => 'GMB', 'is_active' => true]);
    $lead = Lead::factory()->create([
        'name' => 'sachin jadhav',
        'company' => 'new SURYA  CABLE',
        'phone' => '918855973777',
        'source' => LeadSource::MetaAds,
        'service_id' => $gmb->id,
        'utm_campaign' => 'Google Visibility Campaign',
        'estimated_value' => null,
        'meta_leadgen_id' => 'lg_'.uniqid(),
    ]);
    $lead->notes()->create([
        'user_id' => null,
        'body' => "Also submitted a Meta Ads form (Campaign: Google Visibility Campaign).\n\nAdditional form answers:\nwhat's_your_monthly_budget_for_google_visibility?: newSURYA CABLE\ncity: Delhi\nwhat_is_your_biggest_goal?: rank_higher_on_google",
    ]);

    $response = $this->getJson('/api/leads/context?phone=918855973777', ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->json();

    expect($response['found'])->toBeTrue()
        ->and($response['name'])->toBe('sachin jadhav')
        ->and($response['company'])->toBe('new SURYA  CABLE')
        ->and($response['service'])->toBe('GMB')
        ->and($response['campaign'])->toBe('Google Visibility Campaign')
        ->and($response['estimated_value_rupees'])->toBeNull()
        ->and($response['budget_question_raw_answer'])->toBe('newSURYA CABLE')
        ->and($response['additional_answers'])->toContain('city: Delhi')
        ->and($response['additional_answers'])->not->toContain('budget')
        ->and($response['visibility_audit_offer_url'])->toContain('/offers/visibility-audit/enter')
        ->and($response['visibility_audit_offer_url'])->toContain('lead='.$lead->id);
});

it('formats a real parsed budget in rupees, not paise', function () {
    $lead = Lead::factory()->create(['phone' => '919876543211', 'estimated_value' => 1500000]);

    $response = $this->getJson('/api/leads/context?phone=919876543211', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['estimated_value_rupees'])->toBe(15000);
});

it('omits the Visibility Audit offer link for a lead outside the cohort', function () {
    $lead = Lead::factory()->create(['phone' => '919876543212']);

    $response = $this->getJson('/api/leads/context?phone=919876543212', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['found'])->toBeTrue()
        ->and($response['visibility_audit_offer_url'])->toBeNull();
});

it('includes the offer link for a lead with an existing funnel event even without meta_leadgen_id', function () {
    $lead = Lead::factory()->create(['phone' => '919876543213']);
    VisibilityAuditFunnelEvent::create(['event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $lead->id]);

    $response = $this->getJson('/api/leads/context?phone=919876543213', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['visibility_audit_offer_url'])->not->toBeNull();
});

it('does not match a lead that already converted', function () {
    Lead::factory()->create(['phone' => '919876543214', 'status' => LeadStatus::Converted]);

    $this->getJson('/api/leads/context?phone=919876543214', ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['found' => false]);
});

it('matches by last 10 digits when the stored number has a country code and the query does not, or vice versa', function () {
    Lead::factory()->create(['phone' => '+919876543215']);

    $this->getJson('/api/leads/context?phone=9876543215', ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['found' => true]);
});

it('returns null additional_answers and null budget when the lead has no matching note', function () {
    $lead = Lead::factory()->create(['phone' => '919876543216']);

    $response = $this->getJson('/api/leads/context?phone=919876543216', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['budget_question_raw_answer'])->toBeNull()
        ->and($response['additional_answers'])->toBeNull();
});
