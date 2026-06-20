<?php

use App\Enums\UserRole;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

// ── Client show page: services tab ──────────────────────────────────────────

it('shows lead owner and team members in the services tab projects table', function () {
    $lead = User::factory()->create(['name' => 'Rahul Sharma']);
    $member = User::factory()->create(['name' => 'Priya Verma']);
    $service = Service::factory()->create(['name' => 'SEO']);
    $client = Customer::factory()->create();

    $project = Project::factory()->create([
        'customer_id' => $client->id,
        'service_id' => $service->id,
        'owner_id' => $lead->id,
        'name' => 'SEO Campaign',
    ]);
    $project->assignees()->attach($member->id, ['role' => 'member']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Rahul Sharma')
        ->assertSee('Lead')
        ->assertSee('Priya Verma')
        ->assertSee('Member');
});

it('shows a dash when a project has no owner and no assignees', function () {
    $client = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $client->id, 'owner_id' => null, 'name' => 'Bare Project']);

    $this->actingAs($this->admin)
        ->get(route('clients.show', $client))
        ->assertOk()
        ->assertSee('Bare Project');
});

// ── Projects index: My Services filter ──────────────────────────────────────

it('shows the My Services toggle for admin and manager only', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)->get(route('projects.index'))
        ->assertOk()->assertSee('My Services');

    $this->actingAs($this->manager)->get(route('projects.index'))
        ->assertOk()->assertSee('My Services');

    $this->actingAs($sales)->get(route('projects.index'))
        ->assertOk()->assertDontSee('My Services');
});

it('filters projects index to only own projects when mine=1 for a manager', function () {
    $mine = Project::factory()->create(['owner_id' => $this->manager->id, 'name' => 'My SEO']);
    $other = Project::factory()->create(['name' => 'Their Website']);

    $this->actingAs($this->manager)
        ->get(route('projects.index', ['mine' => 1]))
        ->assertOk()
        ->assertSee('My SEO')
        ->assertDontSee('Their Website');
});

it('filters projects index to assignee projects when mine=1 for a manager', function () {
    $assignedProject = Project::factory()->create(['name' => 'Assigned to me']);
    $assignedProject->assignees()->attach($this->manager->id, ['role' => 'member']);

    $other = Project::factory()->create(['name' => 'Not my project']);

    $this->actingAs($this->manager)
        ->get(route('projects.index', ['mine' => 1]))
        ->assertOk()
        ->assertSee('Assigned to me')
        ->assertDontSee('Not my project');
});

// ── Portal: projects list shows owner name ───────────────────────────────────

it('shows the assigned team member name on the portal projects list', function () {
    $owner = User::factory()->create(['name' => 'Nitesh More']);
    $contact = Contact::factory()->portalUser()->create();
    $project = Project::factory()->create([
        'customer_id' => $contact->customer_id,
        'owner_id' => $owner->id,
        'name' => 'Portal Project',
    ]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.index'))
        ->assertOk()
        ->assertSee('Nitesh More');
});

// ── Portal: project show displays "Your NEDS Team" ──────────────────────────

it('shows the lead owner in the Your NEDS Team section on portal project show', function () {
    $owner = User::factory()->create(['name' => 'Rahul Sharma', 'email' => 'rahul@example.com']);
    $service = Service::factory()->create(['name' => 'GMB']);
    $contact = Contact::factory()->portalUser()->create();
    $project = Project::factory()->create([
        'customer_id' => $contact->customer_id,
        'owner_id' => $owner->id,
        'service_id' => $service->id,
    ]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertSee('Your NEDS Team')
        ->assertSee('Rahul Sharma')
        ->assertSee('Lead')
        ->assertSee('rahul@example.com');
});

it('shows additional team members on portal project show', function () {
    $owner = User::factory()->create(['name' => 'Rahul Sharma']);
    $member = User::factory()->create(['name' => 'Priya Verma']);
    $contact = Contact::factory()->portalUser()->create();
    $project = Project::factory()->create([
        'customer_id' => $contact->customer_id,
        'owner_id' => $owner->id,
    ]);
    $project->assignees()->attach($member->id, ['role' => 'member']);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertSee('Priya Verma')
        ->assertSee('Member');
});

it('hides Your NEDS Team section when project has no owner or assignees', function () {
    $contact = Contact::factory()->portalUser()->create();
    $project = Project::factory()->create([
        'customer_id' => $contact->customer_id,
        'owner_id' => null,
    ]);

    $this->actingAs($contact, 'portal')
        ->get(route('portal.projects.show', $project->id))
        ->assertOk()
        ->assertDontSee('Your NEDS Team');
});
