<?php

use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->sales = User::factory()->role(UserRole::Sales)->create();
});

it('logs a call against a client and returns to the client', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'duration_minutes' => 5,
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('clients.show', $customer));

    $call = CallLog::firstOrFail();
    expect($call->user_id)->toBe($this->sales->id)
        ->and($call->callable_type)->toBe(Customer::class)
        ->and($call->callable_id)->toBe($customer->id);

    // Appears in the client's timeline relation.
    expect($customer->callLogs()->count())->toBe(1);
});

it('logs a call against a lead', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->sales)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'incoming',
        'outcome' => 'follow_up_needed',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('leads.show', $lead));

    expect($lead->callLogs()->count())->toBe(1);
});

it('shows a staff member only their own calls but managers all', function () {
    $other = User::factory()->role(UserRole::Sales)->create();
    CallLog::factory()->create(['user_id' => $this->sales->id, 'notes' => 'mine call']);
    CallLog::factory()->create(['user_id' => $other->id, 'notes' => 'other call']);

    $this->actingAs($this->sales)->get(route('calls.index'))->assertOk()
        ->assertSee('mine call')->assertDontSee('other call');

    $this->actingAs(User::factory()->role(UserRole::Manager)->create())->get(route('calls.index'))->assertOk()
        ->assertSee('mine call')->assertSee('other call');
});

it('filters calls by outcome', function () {
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'connected', 'notes' => 'connected one']);
    CallLog::factory()->create(['user_id' => $this->sales->id, 'outcome' => 'busy', 'notes' => 'busy one']);

    $this->actingAs($this->sales)->get(route('calls.index', ['outcome' => 'busy']))->assertOk()
        ->assertSee('busy one')->assertDontSee('connected one');
});

it('renders the call create and index pages', function () {
    $this->actingAs($this->sales)->get(route('calls.index'))->assertOk()->assertSee('Calling');
    $this->actingAs($this->sales)->get(route('calls.create'))->assertOk()->assertSee('Log a Call');
});

it('does not let support log a call against a lead', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($support)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertSessionHasErrors('lead_id');

    expect(CallLog::count())->toBe(0);
});

it('still lets support log a call against a client', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();

    $this->actingAs($support)->post(route('calls.store'), [
        'customer_id' => $customer->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'called_at' => now()->format('Y-m-d H:i:s'),
    ])->assertRedirect(route('clients.show', $customer));

    expect(CallLog::count())->toBe(1);
});

it('does not offer the lead dropdown to support on the log-a-call form', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    Lead::factory()->create(['name' => 'Hidden Lead']);

    $this->actingAs($support)->get(route('calls.create'))
        ->assertOk()
        ->assertDontSee('Hidden Lead')
        ->assertDontSee('…or Lead');
});
