<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('creates a lead and converts the rupee value to paise', function () {
    $this->actingAs($this->admin)
        ->post(route('leads.store'), [
            'name' => 'Priya Shah',
            'company' => 'Shah Traders',
            'email' => 'priya@shah.test',
            'source' => LeadSource::Referral->value,
            'estimated_value' => '5000', // rupees
            'status' => LeadStatus::New->value,
        ])
        ->assertRedirect();

    $lead = Lead::firstWhere('name', 'Priya Shah');

    expect($lead)->not->toBeNull()
        ->and($lead->estimated_value)->toBe(500000) // paise
        ->and($lead->source)->toBe(LeadSource::Referral)
        ->and($lead->status)->toBe(LeadStatus::New);
});

it('requires a name and a valid source', function () {
    $this->actingAs($this->admin)
        ->post(route('leads.store'), ['source' => 'invalid', 'status' => LeadStatus::New->value])
        ->assertSessionHasErrors(['name', 'source']);
});

it('does not allow setting status directly to converted', function () {
    $this->actingAs($this->admin)
        ->post(route('leads.store'), [
            'name' => 'X',
            'source' => LeadSource::Other->value,
            'status' => LeadStatus::Converted->value,
        ])
        ->assertSessionHasErrors('status');
});

it('updates and soft deletes a lead', function () {
    $lead = Lead::factory()->create(['name' => 'Old']);

    $this->actingAs($this->admin)
        ->put(route('leads.update', $lead), [
            'name' => 'Updated',
            'source' => $lead->source->value,
            'status' => LeadStatus::Contacted->value,
        ])
        ->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()->name)->toBe('Updated');

    $this->actingAs($this->admin)->delete(route('leads.destroy', $lead))->assertRedirect(route('leads.index'));
    $this->assertSoftDeleted($lead);
});

it('renders the lead index, create, show and edit pages', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee('Lead Generation');
    $this->actingAs($this->admin)->get(route('leads.create'))->assertOk()->assertSee('Contact name');
    $this->actingAs($this->admin)->get(route('leads.show', $lead))->assertOk()->assertSee($lead->name);
    $this->actingAs($this->admin)->get(route('leads.edit', $lead))->assertOk()->assertSee('Save Changes');
});
