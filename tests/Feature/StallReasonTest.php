<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Enums\DealStage;
use App\Enums\StallReason;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('lets an authorized user tag a lead\'s stall reason', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.stall-reason.update', $lead), ['stall_reason' => StallReason::Budget->value])
        ->assertRedirect();

    expect($lead->fresh()->stall_reason)->toBe(StallReason::Budget);
});

it('clears a lead\'s stall reason when the picker is set back to blank', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'stall_reason' => StallReason::Trust]);

    $this->actingAs($sales)->post(route('leads.stall-reason.update', $lead), ['stall_reason' => '']);

    expect($lead->fresh()->stall_reason)->toBeNull();
});

it('rejects an invalid stall reason value on a lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('leads.stall-reason.update', $lead), ['stall_reason' => 'not-a-real-reason'])
        ->assertSessionHasErrors('stall_reason');
});

it('forbids a Sales rep from tagging another rep\'s lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $other->id]);

    $this->actingAs($sales)
        ->post(route('leads.stall-reason.update', $lead), ['stall_reason' => StallReason::Budget->value])
        ->assertForbidden();
});

it('lets an authorized user tag a deal\'s stall reason', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->create(['owner_id' => $sales->id]);

    $this->actingAs($sales)
        ->post(route('deals.stall-reason.update', $deal), ['stall_reason' => StallReason::Competitor->value])
        ->assertRedirect();

    expect($deal->fresh()->stall_reason)->toBe(StallReason::Competitor);
});

it('shows the stall-reason picker on the lead show page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($manager)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Stalling on:')
        ->assertSee('Budget / financial constraint');
});

it('shows the stall-reason picker on an open deal\'s page but not on a Won one', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $open = Deal::factory()->create(['stage' => DealStage::Proposal]);
    $won = Deal::factory()->create(['stage' => DealStage::Won, 'won_at' => now()]);

    $this->actingAs($manager)->get(route('deals.show', $open))->assertOk()->assertSee('Stalling on:');
    $this->actingAs($manager)->get(route('deals.show', $won))->assertOk()->assertDontSee('Stalling on:');
});

it('shows the stall-reason field on the Log a Call form only when a lead is preselected', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)->get(route('calls.create', ['lead_id' => $lead->id]))
        ->assertOk()->assertSee("Stalling on? (only if this lead has real history but isn't moving forward)");

    $this->actingAs($sales)->get(route('calls.create', ['customer_id' => $customer->id]))
        ->assertOk()->assertDontSee("Stalling on? (only if this lead has real history but isn't moving forward)");
});

it('tags the lead\'s stall reason when logging a call with one selected', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
        'stall_reason' => StallReason::Confused->value,
    ]);

    expect($lead->fresh()->stall_reason)->toBe(StallReason::Confused);
});

it('never clears an existing stall reason just because the Log a Call form left it blank', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['stall_reason' => StallReason::Budget]);

    $this->actingAs($sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
    ]);

    expect($lead->fresh()->stall_reason)->toBe(StallReason::Budget);
});

it('does not apply a stall reason to a Customer when logging a call against one', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create();

    $this->actingAs($sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
        'stall_reason' => StallReason::Budget->value,
    ]);

    expect(CallLog::where('callable_type', Customer::class)->where('callable_id', $customer->id)->exists())->toBeTrue();
    // No assertion target on Customer -- it simply has no such column; this
    // just confirms the call itself still saved fine with the field present.
});

it('shows a soft warning on the Log a Call form when Connected has no follow-up date, without blocking submission', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($sales)->get(route('calls.create', ['lead_id' => $lead->id]))
        ->assertOk()
        ->assertSee("outcome === 'connected' && !followUpAt", false);

    // Submitting with Connected and no follow_up_at must still succeed.
    $this->actingAs($sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => CallDirection::Outgoing->value,
        'outcome' => CallOutcome::Connected->value,
        'called_at' => now()->format('Y-m-d\TH:i'),
    ])->assertRedirect();
});
