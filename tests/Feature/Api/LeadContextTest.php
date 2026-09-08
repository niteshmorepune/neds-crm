<?php

use App\Enums\LeadGoal;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Models\Lead;
use App\Models\Service;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Notifications\LeadWantsExpertAdviceNotification;
use Illuminate\Support\Facades\Notification;
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

it('includes goal/needs_link/website_url/gbp_url in the context response', function () {
    Lead::factory()->create([
        'phone' => '919876543217',
        'goal' => LeadGoal::GrowBusiness,
        'website_url' => 'https://example.com',
        'gbp_url' => null,
    ]);

    $response = $this->getJson('/api/leads/context?phone=919876543217', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['goal'])->toBe('grow_business')
        ->and($response['needs_link'])->toBeTrue()
        ->and($response['website_url'])->toBe('https://example.com')
        ->and($response['gbp_url'])->toBeNull();
});

it('reports needs_link=false for a NotSure goal', function () {
    Lead::factory()->create(['phone' => '919876543218', 'goal' => LeadGoal::NotSure]);

    $response = $this->getJson('/api/leads/context?phone=919876543218', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['needs_link'])->toBeFalse();
});

it('reports needs_link=false and goal=null for a lead with no goal set yet', function () {
    Lead::factory()->create(['phone' => '919876543219', 'goal' => null]);

    $response = $this->getJson('/api/leads/context?phone=919876543219', ['Authorization' => 'Bearer test-wa-token'])->json();

    expect($response['goal'])->toBeNull()
        ->and($response['needs_link'])->toBeFalse();
});

it('rejects an unauthenticated goal-capture request', function () {
    $this->postJson('/api/leads/goal-capture', ['phone' => '919876543220'])->assertUnauthorized();
});

it('writes a goal-only update to the matched lead and returns updated=true', function () {
    $lead = Lead::factory()->create(['phone' => '919876543221', 'goal' => null]);

    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919876543221',
        'goal' => LeadGoal::RankHigher->value,
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['updated' => true]);

    expect($lead->fresh()->goal)->toBe(LeadGoal::RankHigher)
        ->and($lead->fresh()->website_url)->toBeNull();
});

it('only writes the fields provided, never clearing the others', function () {
    $lead = Lead::factory()->create([
        'phone' => '919876543222',
        'goal' => LeadGoal::GrowBusiness,
        'website_url' => 'https://already-there.example.com',
    ]);

    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919876543222',
        'gbp_url' => 'https://maps.app.goo.gl/xyz',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $lead->refresh();
    expect($lead->goal)->toBe(LeadGoal::GrowBusiness)
        ->and($lead->website_url)->toBe('https://already-there.example.com')
        ->and($lead->gbp_url)->toBe('https://maps.app.goo.gl/xyz');
});

it('returns updated=false when no lead matches the phone on goal-capture', function () {
    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919999999999',
        'goal' => LeadGoal::RankHigher->value,
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['updated' => false]);
});

it('rejects an invalid goal value on goal-capture', function () {
    Lead::factory()->create(['phone' => '919876543223']);

    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919876543223',
        'goal' => 'not-a-real-goal',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertJsonValidationErrors('goal');
});

it('notifies the lead owner when goal-capture sets goal to NotSure', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['phone' => '919876543224', 'owner_id' => $owner->id, 'goal' => null]);

    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919876543224',
        'goal' => LeadGoal::NotSure->value,
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    Notification::assertSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->lead->is($lead));
});

it('does not re-notify when goal-capture resends the same NotSure value', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['phone' => '919876543225', 'owner_id' => $owner->id, 'goal' => LeadGoal::NotSure]);

    $this->postJson('/api/leads/goal-capture', [
        'phone' => '919876543225',
        'goal' => LeadGoal::NotSure->value,
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class);
});
