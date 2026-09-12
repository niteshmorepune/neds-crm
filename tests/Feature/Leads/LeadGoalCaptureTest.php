<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an authorized user save a lead\'s goal and links', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), [
            'goal' => LeadGoal::RankHigher->value,
            'website_url' => 'https://example.com',
            'gbp_url' => 'https://maps.app.goo.gl/abc123',
        ])
        ->assertRedirect();

    $lead->refresh();
    expect($lead->goal)->toBe(LeadGoal::RankHigher)
        ->and($lead->website_url)->toBe('https://example.com')
        ->and($lead->gbp_url)->toBe('https://maps.app.goo.gl/abc123');
});

it('clears goal and links when the capture form is resubmitted blank', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create([
        'owner_id' => $sales->id,
        'goal' => LeadGoal::GenerateLeads,
        'website_url' => 'https://old.example.com',
    ]);

    $this->actingAs($sales)->post(route('leads.goal-capture.update', $lead), []);

    $lead->refresh();
    expect($lead->goal)->toBeNull()->and($lead->website_url)->toBeNull();
});

it('rejects an invalid goal value', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), ['goal' => 'not-a-real-goal'])
        ->assertSessionHasErrors('goal');
});

it('rejects a non-url website/gbp value', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), ['website_url' => 'not a url'])
        ->assertSessionHasErrors('website_url');
});

it('forbids a Sales rep from capturing goal/links on another rep\'s lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $other->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), ['goal' => LeadGoal::RankHigher->value])
        ->assertForbidden();
});

it('shows the goal/links capture panel on the lead show page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('What are they looking for?')
        ->assertSee('Rank Higher on Google');
});

it('shows a next-step banner asking for the Website/GBP link when the goal needs one and none is captured', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::GrowBusiness, 'website_url' => null, 'gbp_url' => null]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Ask for their Website or GBP link');
});

it('does not show the website/gbp banner once a link has already been captured', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::GrowBusiness, 'website_url' => 'https://example.com']);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Ask for their Website or GBP link');
});

it('collapses the goal/links capture panel to a summary once everything needed is already captured', function () {
    // Both the collapsed-summary and expanded-form markup are always present
    // in the server-rendered HTML — Alpine's x-show only toggles visibility
    // client-side after JS runs, so a Pest HTTP test (no JS execution) can
    // only assert on the PHP-computed `editing:` state in x-data, not on
    // which block is actually visible.
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::NotSure, 'budget_range' => LeadBudgetRange::Under3000]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Goal, budget & links already captured.', false)
        ->assertSee('editing: false')
        ->assertSee('x-show="editing || false"', false);
});

it('keeps the goal/links capture panel expanded while something is still missing', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::NotSure, 'budget_range' => null]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('editing: true');
});

it('collapses the goal/budget dropdowns but keeps the website/gbp inputs live when goal+budget are known but a needed link is still missing', function () {
    // Real gap caught from a live lead (#406): goal+budget were both
    // already known but its goal still needed a Website/GBP link that
    // hadn't been captured yet — the panel should not duplicate the
    // already-known goal/budget as dropdowns, but the link inputs must
    // stay reachable without a click since that's a genuine outstanding
    // ask (mirrors the "Ask for their Website or GBP link" banner).
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create([
        'goal' => LeadGoal::GrowBusiness,
        'budget_range' => LeadBudgetRange::Under3000,
        'website_url' => null,
        'gbp_url' => null,
    ]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('editing: false')
        ->assertSee('Goal: Grow My Business Online', false)
        ->assertSee('Budget: Under ₹3,000', false)
        ->assertDontSee('Goal, budget & links already captured.', false)
        ->assertSee('x-show="editing || true"', false);
});

it('shows a schedule-a-call banner when the lead is Not Sure', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::NotSure]);

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('They want expert advice')
        ->assertSee('Schedule a call with a Sales Expert');
});

it('shows the goal field on the Log a Call form only when a lead is preselected', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($sales)->get(route('calls.create', ['lead_id' => $lead->id]))
        ->assertOk()->assertSee("What's their biggest goal?");

    $this->actingAs($sales)->get(route('calls.create'))
        ->assertOk()->assertDontSee("What's their biggest goal?");
});

it('saves goal and links to the lead when logging a call with them filled in', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
        'goal' => LeadGoal::NotSure->value,
        'website_url' => 'https://example.com',
    ]);

    $lead->refresh();
    expect($lead->goal)->toBe(LeadGoal::NotSure)
        ->and($lead->website_url)->toBe('https://example.com')
        ->and($lead->gbp_url)->toBeNull();
});

it('never clears an existing goal/links just because the Log a Call form left them blank', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['goal' => LeadGoal::RankHigher, 'website_url' => 'https://example.com']);

    $this->actingAs($sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
    ]);

    $lead->refresh();
    expect($lead->goal)->toBe(LeadGoal::RankHigher)
        ->and($lead->website_url)->toBe('https://example.com');
});

it('saves budget_range alongside goal and generates the recommendation immediately', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), [
            'goal' => LeadGoal::GenerateLeads->value,
            'budget_range' => LeadBudgetRange::Under3000->value,
        ])
        ->assertRedirect();

    $lead->refresh();
    expect($lead->goal)->toBe(LeadGoal::GenerateLeads)
        ->and($lead->budget_range)->toBe(LeadBudgetRange::Under3000)
        ->and($lead->recommendation_key)->toBe('lead-generation-audit')
        ->and($lead->recommendation_offer_key)->toBe('lead_generation_audit')
        ->and($lead->recommendation_token)->not->toBeNull();
});

it('rejects an invalid budget_range value', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.goal-capture.update', $lead), ['budget_range' => 'not-a-real-range'])
        ->assertSessionHasErrors('budget_range');
});
