<?php

use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Models\Lead;

it('renders the phone lookup page', function () {
    $this->get(route('offers.find-my-recommendation'))
        ->assertOk()
        ->assertSee('personalized recommendation', false);
});

it('requires a phone number', function () {
    $this->post(route('offers.find-my-recommendation.lookup'), [])
        ->assertSessionHasErrors('phone');
});

it('shows a friendly message when no lead matches the phone number', function () {
    $response = $this->post(route('offers.find-my-recommendation.lookup'), ['phone' => '9999999999']);

    $response->assertOk()->assertSee('find a submission with that number yet', false);
});

it('shows a friendly "still processing" message when the matched lead has no goal/budget yet', function () {
    Lead::factory()->create(['phone' => '9812345601', 'goal' => null, 'budget_range' => null]);

    $response = $this->post(route('offers.find-my-recommendation.lookup'), ['phone' => '9812345601']);

    $response->assertOk()->assertSee('still working out your personalized recommendation', false);
});

it('redirects to the GBP invite tracking hop when the matched lead resolves to GbpAudit', function () {
    $lead = Lead::factory()->create([
        'phone' => '9812345602',
        'goal' => LeadGoal::RankHigher,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    $this->post(route('offers.find-my-recommendation.lookup'), ['phone' => '9812345602'])
        ->assertRedirect(route('offers.visibility-audit.enter', ['lead' => $lead->id]));
});

it('redirects to the lead\'s own recommendation URL when resolved to a non-GBP offer', function () {
    $lead = Lead::factory()->create([
        'phone' => '9812345603',
        'goal' => LeadGoal::GenerateLeads,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    $this->post(route('offers.find-my-recommendation.lookup'), ['phone' => '9812345603'])
        ->assertRedirect($lead->fresh()->recommendationUrl());
});

it('matches the lead via the last-10-digit fallback, same as every other phone lookup in the app', function () {
    $lead = Lead::factory()->create([
        'phone' => '+919812345604',
        'goal' => LeadGoal::RankHigher,
        'budget_range' => LeadBudgetRange::Under3000,
    ]);

    $this->post(route('offers.find-my-recommendation.lookup'), ['phone' => '9812345604'])
        ->assertRedirect(route('offers.visibility-audit.enter', ['lead' => $lead->id]));
});
