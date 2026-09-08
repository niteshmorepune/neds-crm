<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
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
