<?php

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\Lead;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Str;

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

it('saves and displays an alternate phone number', function () {
    $this->actingAs($this->admin)->post(route('leads.store'), [
        'name' => 'Priya Shah',
        'phone' => '9876543210',
        'alternate_phone' => '9123456780',
        'source' => LeadSource::Referral->value,
        'status' => LeadStatus::New->value,
    ])->assertRedirect();

    $lead = Lead::firstWhere('name', 'Priya Shah');
    expect($lead->alternate_phone)->toBe('9123456780');

    $this->actingAs($this->admin)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('9123456780');
});

it('does not show an Alternate phone row when none is set', function () {
    $lead = Lead::factory()->create(['alternate_phone' => null]);

    $this->actingAs($this->admin)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertDontSee('Alternate phone');
});

it('shows a logged call on the lead show page', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin)->post(route('calls.store'), [
        'lead_id' => $lead->id,
        'direction' => 'outgoing',
        'outcome' => 'connected',
        'duration_minutes' => 5,
        'called_at' => now()->format('Y-m-d H:i:s'),
        'notes' => 'Discussed pricing, will follow up Friday.',
    ])->assertRedirect(route('leads.show', $lead));

    $this->actingAs($this->admin)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('Discussed pricing, will follow up Friday.')
        ->assertSee('Outgoing')
        ->assertSee('Connected');
});

it('shows "No calls logged" on a lead with no call history', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin)->get(route('leads.show', $lead))
        ->assertOk()
        ->assertSee('No calls logged');
});

it('filters leads to those with a due follow-up via the follow_up_due flag', function () {
    Lead::factory()->dueFollowUp()->create(['name' => 'Overdue follow-up']);
    Lead::factory()->create(['name' => 'No follow-up set', 'next_follow_up_at' => null]);
    Lead::factory()->create(['name' => 'Future follow-up', 'next_follow_up_at' => now()->addDay()]);
    Lead::factory()->dueFollowUp()->create(['name' => 'Already converted', 'status' => LeadStatus::Converted]);

    $this->actingAs($this->admin)->get(route('leads.index', ['follow_up_due' => 1]))
        ->assertOk()->assertSee('Overdue follow-up')
        ->assertDontSee('No follow-up set')
        ->assertDontSee('Future follow-up')
        ->assertDontSee('Already converted');
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

it('saves the next follow-up time in IST, not UTC', function () {
    $lead = Lead::factory()->create(['name' => 'Pradeep Ranaware']);

    $this->actingAs($this->admin)
        ->put(route('leads.update', $lead), [
            'name' => $lead->name,
            'source' => $lead->source->value,
            'status' => LeadStatus::New->value,
            'next_follow_up_at' => '2026-08-01T13:10',
        ])
        ->assertRedirect(route('leads.show', $lead));

    expect($lead->fresh()->next_follow_up_at->toIso8601String())->toBe('2026-08-01T07:40:00+00:00');
});

it('renders the lead index, create, show and edit pages', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee('Lead Generation');
    $this->actingAs($this->admin)->get(route('leads.create'))->assertOk()->assertSee('Contact name');
    $this->actingAs($this->admin)->get(route('leads.show', $lead))->assertOk()->assertSee($lead->name);
    $this->actingAs($this->admin)->get(route('leads.edit', $lead))->assertOk()->assertSee('Save Changes');
});

it('shows an inline hint clarifying Qualified vs Converted on the lead form', function () {
    $lead = Lead::factory()->create();

    $this->actingAs($this->admin)->get(route('leads.create'))->assertOk()
        ->assertSee('Qualified = real budget & need confirmed', false);
    $this->actingAs($this->admin)->get(route('leads.edit', $lead))->assertOk()
        ->assertSee('Converted = this lead became a real Deal + Client');
});

it('shows summary cards with lead counts per status, unaffected by list filters', function () {
    Lead::factory()->create(['status' => LeadStatus::New]);
    Lead::factory()->count(2)->create(['status' => LeadStatus::Contacted]);
    Lead::factory()->create(['status' => LeadStatus::Qualified]);
    Lead::factory()->create(['status' => LeadStatus::Converted]);
    Lead::factory()->count(3)->create(['status' => LeadStatus::Lost]);

    // Filtering the list to just one status must not change the summary cards.
    $response = $this->actingAs($this->admin)->get(route('leads.index', ['status' => LeadStatus::Lost->value]));

    $response->assertOk()
        ->assertViewHas('statusCounts', [
            'total' => 8,
            'new' => 1,
            'contacted' => 2,
            'qualified' => 1,
            'converted' => 1,
            'lost' => 3,
        ]);
});

it('shows the most recent note in the Latest Note column, truncated with the full text available on hover', function () {
    $lead = Lead::factory()->create(['name' => 'Ganesh Auto Parts']);
    $lead->notes()->create(['user_id' => $this->admin->id, 'body' => 'Called, no answer.']);
    $longNote = str_repeat('Discussed pricing and scope in detail. ', 3);
    $lead->notes()->create(['user_id' => $this->admin->id, 'body' => $longNote]);

    $response = $this->actingAs($this->admin)->get(route('leads.index'));

    $response->assertOk()
        ->assertSee(Str::limit($longNote, 60), false)
        ->assertDontSee('Called, no answer.')
        ->assertSee($longNote, false);
});

it('shows a dash in the Latest Note column when a lead has no notes', function () {
    Lead::factory()->create(['name' => 'No Notes Yet']);

    $this->actingAs($this->admin)->get(route('leads.index'))->assertOk()->assertSee('—');
});

it('filters the leads list to a specific capture month', function () {
    Lead::factory()->create(['name' => 'August lead', 'created_at' => '2026-08-05']);
    Lead::factory()->create(['name' => 'July lead', 'created_at' => '2026-07-05']);

    $this->actingAs($this->admin)->get(route('leads.index', ['month' => '2026-08']))
        ->assertOk()
        ->assertSee('August lead')
        ->assertDontSee('July lead');
});

it('ignores a malformed month filter on the leads list instead of erroring', function () {
    Lead::factory()->create(['name' => 'Visible lead']);

    $this->actingAs($this->admin)
        ->get(route('leads.index', ['month' => 'garbage']))
        ->assertOk()->assertSee('Visible lead');
});
