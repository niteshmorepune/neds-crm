<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
});

it('shows the merge review page for exactly 2 selected leads', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $leadA = Lead::factory()->create(['name' => 'Ganesh Auto Parts']);
    $leadB = Lead::factory()->create(['name' => 'Ganesh Autoparts Pvt Ltd']);

    $this->actingAs($admin)
        ->get(route('leads.merge.show', ['ids' => [$leadA->id, $leadB->id]]))
        ->assertOk()
        ->assertSee('Ganesh Auto Parts')
        ->assertSee('Ganesh Autoparts Pvt Ltd');
});

it('redirects back with an error when fewer than 2 leads are selected', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)
        ->get(route('leads.merge.show', ['ids' => [$lead->id]]))
        ->assertRedirect(route('leads.index'));
});

it('redirects back with an error when more than 2 leads are selected', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $leads = Lead::factory()->count(3)->create();

    $this->actingAs($admin)
        ->get(route('leads.merge.show', ['ids' => $leads->pluck('id')->all()]))
        ->assertRedirect(route('leads.index'));
});

it('forbids a Telecaller from viewing or performing a merge, though they can otherwise use the Leads page', function () {
    // Telecaller deliberately excluded from LeadPolicy::merge() (Admin/Manager/Sales
    // only) — unlike a role with no lead-generation access at all, this proves
    // the merge *ability* itself is checked, not just page-level menu access.
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    $this->actingAs($telecaller)
        ->get(route('leads.merge.show', ['ids' => [$leadA->id, $leadB->id]]))
        ->assertForbidden();
});

it('merges two leads end to end, applying the chosen field per column and redirecting to the survivor', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $leadA = Lead::factory()->create(['name' => 'Ganesh Auto Parts', 'phone' => '9111111111', 'source' => LeadSource::Referral->value, 'owner_id' => null]);
    $leadB = Lead::factory()->create(['name' => 'Ganesh Autoparts', 'phone' => '9222222222', 'source' => LeadSource::Website->value, 'owner_id' => $sales->id]);

    $this->actingAs($admin)->post(route('leads.merge.store'), [
        'primary_id' => $leadA->id,
        'duplicate_id' => $leadB->id,
        'field_source' => [
            'name' => $leadB->id,    // keep B's name
            'company' => $leadA->id,
            'phone' => $leadA->id,   // keep A's phone
            'email' => $leadA->id,
            'source' => $leadA->id,
            'service_id' => $leadA->id,
            'estimated_value' => $leadA->id,
            'owner_id' => $leadB->id, // keep B's owner
            'status' => $leadA->id,
        ],
    ])->assertRedirect(route('leads.show', $leadA->id));

    $leadA->refresh();
    expect($leadA->name)->toBe('Ganesh Autoparts')
        ->and($leadA->phone)->toBe('9111111111')
        ->and($leadA->owner_id)->toBe($sales->id);

    expect(Lead::find($leadB->id))->toBeNull();
    $this->assertSoftDeleted($leadB);
});

it('rejects a field_source value that is neither of the two leads being merged', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();
    $unrelatedLead = Lead::factory()->create();

    $this->actingAs($admin)->post(route('leads.merge.store'), [
        'primary_id' => $leadA->id,
        'duplicate_id' => $leadB->id,
        'field_source' => array_fill_keys(
            ['name', 'company', 'phone', 'email', 'source', 'service_id', 'estimated_value', 'owner_id', 'status'],
            $unrelatedLead->id,
        ),
    ])->assertSessionHasErrors('field_source.name');

    expect(Lead::find($leadB->id))->not->toBeNull();
});

it('rejects a missing field in field_source', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    $this->actingAs($admin)->post(route('leads.merge.store'), [
        'primary_id' => $leadA->id,
        'duplicate_id' => $leadB->id,
        'field_source' => ['name' => $leadA->id], // missing every other field
    ])->assertSessionHasErrors('field_source');
});

it('rejects primary_id and duplicate_id being the same lead', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $lead = Lead::factory()->create();

    $this->actingAs($admin)->post(route('leads.merge.store'), [
        'primary_id' => $lead->id,
        'duplicate_id' => $lead->id,
        'field_source' => array_fill_keys(
            ['name', 'company', 'phone', 'email', 'source', 'service_id', 'estimated_value', 'owner_id', 'status'],
            $lead->id,
        ),
    ])->assertSessionHasErrors('primary_id');
});

it('forbids a Telecaller from posting a merge directly', function () {
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    $leadA = Lead::factory()->create();
    $leadB = Lead::factory()->create();

    $this->actingAs($telecaller)->post(route('leads.merge.store'), [
        'primary_id' => $leadA->id,
        'duplicate_id' => $leadB->id,
        'field_source' => array_fill_keys(
            ['name', 'company', 'phone', 'email', 'source', 'service_id', 'estimated_value', 'owner_id', 'status'],
            $leadA->id,
        ),
    ])->assertForbidden();

    expect(Lead::find($leadB->id))->not->toBeNull();
});

it('shows the Merge Selected control on the leads index for a Sales user but not a Telecaller', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $telecaller = User::factory()->role(UserRole::Telecaller)->create();
    Lead::factory()->create();

    $this->actingAs($sales)->get(route('leads.index'))->assertOk()->assertSee('Merge Selected');
    $this->actingAs($telecaller)->get(route('leads.index'))->assertOk()->assertDontSee('Merge Selected');
});
