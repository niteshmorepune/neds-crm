<?php

use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Livewire\RecordNotes;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->staff = User::factory()->create();
});

// ──────────────────────────────────────────────────────────────────────────────
// Component-level gating
// ──────────────────────────────────────────────────────────────────────────────

it('offers the "This was a call" shortcut for a Lead with canManage', function () {
    $lead = Lead::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->call('canLogAsCall')
        ->assertReturned(true);
});

it('offers the shortcut for a Customer with canManage', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $customer, 'canManage' => true])
        ->call('canLogAsCall')
        ->assertReturned(true);
});

it('does not offer the shortcut without canManage', function () {
    $lead = Lead::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => false, 'canAddNotes' => true])
        ->call('canLogAsCall')
        ->assertReturned(false);
});

// ──────────────────────────────────────────────────────────────────────────────
// addNote() with logAsCall
// ──────────────────────────────────────────────────────────────────────────────

it('creates a CallLog and promotes a New lead to Contacted when logged as a call', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Called, said he is out of town, will confirm next week.')
        ->set('logAsCall', true)
        ->set('callOutcome', CallOutcome::FollowUpNeeded->value)
        ->call('addNote')
        ->assertHasNoErrors()
        ->assertSet('logAsCall', false); // reset after submit

    expect($lead->callLogs()->count())->toBe(1);

    $call = $lead->callLogs()->first();
    expect($call->outcome)->toBe(CallOutcome::FollowUpNeeded)
        ->and($call->notes)->toBe('Called, said he is out of town, will confirm next week.')
        ->and($call->user_id)->toBe($this->staff->id);

    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted);
});

it('does not touch status for a lead already past New', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Follow-up call, still deciding.')
        ->set('logAsCall', true)
        ->set('callOutcome', CallOutcome::Connected->value)
        ->call('addNote')
        ->assertHasNoErrors();

    expect($lead->callLogs()->count())->toBe(1);
    expect($lead->fresh()->status)->toBe(LeadStatus::Contacted);
});

it('logs a call against a Customer without touching any lead-only status logic', function () {
    $customer = Customer::factory()->create();

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $customer, 'canManage' => true])
        ->set('body', 'Discussed renewal.')
        ->set('logAsCall', true)
        ->set('callOutcome', CallOutcome::Connected->value)
        ->call('addNote')
        ->assertHasNoErrors();

    expect($customer->callLogs()->count())->toBe(1);
});

it('requires an outcome when logAsCall is checked', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Called him.')
        ->set('logAsCall', true)
        ->set('callOutcome', null)
        ->call('addNote')
        ->assertHasErrors('callOutcome');

    expect($lead->callLogs()->count())->toBe(0);
    expect($lead->notes()->count())->toBe(0); // whole submission short-circuits, note not created either
    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});

it('still creates a plain note with no CallLog when logAsCall is left unchecked', function () {
    $lead = Lead::factory()->create(['status' => LeadStatus::New]);

    Livewire::actingAs($this->staff)
        ->test(RecordNotes::class, ['record' => $lead, 'canManage' => true])
        ->set('body', 'Internal note only, not a call.')
        ->call('addNote')
        ->assertHasNoErrors();

    expect($lead->notes()->count())->toBe(1);
    expect($lead->callLogs()->count())->toBe(0);
    expect($lead->fresh()->status)->toBe(LeadStatus::New);
});
