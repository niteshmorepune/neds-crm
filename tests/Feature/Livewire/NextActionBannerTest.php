<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Livewire\NextActionBanner;
use App\Models\Lead;
use App\Models\NextActionSnooze;
use App\Models\User;
use Livewire\Livewire;

it('shows the pending lead-call prompt to a Sales rep', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New, 'name' => 'Priya Deshmukh']);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSee('Call Priya Deshmukh')
        ->assertSee(route('calls.create', ['lead_id' => $lead->id]));
});

it('shows nothing when there is no pending action', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertDontSee('Call ')
        ->assertSet('action', null);
});

it('never shows a prompt to a non-Sales user', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['owner_id' => $support->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($support)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);
});

it('snoozes the current prompt, creating a NextActionSnooze row and clearing it from the next poll', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action.subject_id', $lead->id)
        ->call('snooze')
        ->assertSet('action', null);

    expect(NextActionSnooze::where('user_id', $sales->id)
        ->where('subject_type', Lead::class)
        ->where('subject_id', $lead->id)
        ->where('snoozed_until', '>', now())
        ->exists())->toBeTrue();
});

it('poll re-evaluates and picks up a newly-created lead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $component = Livewire::actingAs($sales)
        ->test(NextActionBanner::class)
        ->assertSet('action', null);

    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'status' => LeadStatus::New]);

    $component->call('poll')->assertSet('action.subject_id', $lead->id);
});
