<?php

use App\Actions\CreateProjectFromDeal;
use App\Enums\DealStage;
use App\Enums\DeliverableStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('creates a project from a won deal carrying over customer/service/owner', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $deal = Deal::factory()->stage(DealStage::Won)->ownedBy($owner->id)->create();

    $project = app(CreateProjectFromDeal::class)->handle($deal);

    expect($project->customer_id)->toBe($deal->customer_id)
        ->and($project->deal_id)->toBe($deal->id)
        ->and($project->owner_id)->toBe($owner->id)
        ->and($project->assignees()->whereKey($owner->id)->exists())->toBeTrue();
});

it('refuses to create a project from a deal that is not won', function () {
    $deal = Deal::factory()->stage(DealStage::Proposal)->create();

    expect(fn () => app(CreateProjectFromDeal::class)->handle($deal))->toThrow(RuntimeException::class);
});

it('does not create a second project for the same deal', function () {
    $deal = Deal::factory()->stage(DealStage::Won)->create();

    $this->actingAs($this->manager)->post(route('projects.from-deal', $deal))->assertRedirect();
    $this->actingAs($this->manager)->post(route('projects.from-deal', $deal))->assertRedirect();

    expect(Project::where('deal_id', $deal->id)->count())->toBe(1);
});

it('creates a project via the form and syncs assignees', function () {
    $a = User::factory()->create();
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)->post(route('projects.store'), [
        'name' => 'Website build', 'customer_id' => $customer->id,
        'status' => 'active', 'assignees' => [$a->id],
    ])->assertRedirect();

    $project = Project::firstWhere('name', 'Website build');
    expect($project->assignees()->whereKey($a->id)->exists())->toBeTrue();
});

it('defaults a new project\'s client requirement status to Pending, and lets it be set explicitly on update', function () {
    $customer = Customer::factory()->create();

    $this->actingAs($this->manager)->post(route('projects.store'), [
        'name' => 'Requirement Status Co', 'customer_id' => $customer->id, 'status' => 'active',
    ])->assertRedirect();

    $project = Project::firstWhere('name', 'Requirement Status Co');
    expect($project->requirement_status)->toBe(DeliverableStatus::Pending);

    $this->actingAs($this->manager)->put(route('projects.update', $project), [
        'name' => $project->name, 'customer_id' => $customer->id, 'status' => 'active',
        'requirement_status' => 'received',
    ])->assertRedirect();

    expect($project->fresh()->requirement_status)->toBe(DeliverableStatus::Received);
});

it('shows the Client Requirement Status badge on the project page', function () {
    $project = Project::factory()->create(['requirement_status' => 'submitted']);

    $this->actingAs($this->manager)->get(route('projects.show', $project))->assertOk()
        ->assertSee('Client Requirement Status')
        ->assertSee('Submitted');
});

it('restricts project visibility for non-managers to owned/assigned', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $outsider = User::factory()->role(UserRole::Sales)->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    expect($owner->can('view', $project))->toBeTrue()
        ->and($outsider->can('view', $project))->toBeFalse();

    $this->actingAs($outsider)->get(route('projects.show', $project))->assertForbidden();
});

it('renders project index, create and show pages', function () {
    $project = Project::factory()->create(['owner_id' => $this->manager->id]);

    $this->actingAs($this->manager)->get(route('projects.index'))->assertOk()->assertSee('Project Updates');
    $this->actingAs($this->manager)->get(route('projects.create'))->assertOk()->assertSee('Project name')->assertSee('Project Manager');
    $this->actingAs($this->manager)->get(route('projects.show', $project))->assertOk()->assertSee($project->name)->assertSee('Project Manager:');
});

it('shows a Log Invoice link on the project page, preselecting this client and project', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $customer->id]);

    $html = $this->actingAs($this->manager)->get(route('projects.show', $project))->assertOk()->getContent();

    expect($html)->toContain(route('invoices.create'))
        ->toContain('customer_id='.$customer->id)
        ->toContain('project_id='.$project->id);
});

it('lists a project\'s own invoices, plainly, when billed directly to the project\'s own client', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $customer->id]);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'project_id' => $project->id, 'invoice_number' => 'NEDS/2026-27/0001']);

    $html = $this->actingAs($this->manager)->get(route('projects.show', $project))->assertOk()->getContent();

    expect($html)->toContain('NEDS/2026-27/0001')
        ->not->toContain('Billed via');
});

it('flags a project\'s invoice as "Billed via X" when it was reseller-billed to a different customer', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz']);
    $client = Customer::factory()->create(['company_name' => 'TMR']);
    $project = Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $client->id]);
    Invoice::factory()->create(['customer_id' => $billTo->id, 'project_id' => $project->id, 'invoice_number' => 'NEDS/2026-27/0002']);

    $html = $this->actingAs($this->manager)->get(route('projects.show', $project))->assertOk()->getContent();

    expect($html)->toContain('NEDS/2026-27/0002')
        ->toContain('Billed via Brand Whiz');
});

it('hides the Invoices section from a role without invoice-view access, on the project page', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $project = Project::factory()->create(['owner_id' => $support->id]);
    Invoice::factory()->create(['customer_id' => $project->customer_id, 'project_id' => $project->id, 'invoice_number' => 'NEDS/2026-27/0003']);

    $html = $this->actingAs($support)->get(route('projects.show', $project))->assertOk()->getContent();

    expect($html)->not->toContain('NEDS/2026-27/0003');
});

it('hides the Log Invoice link from a role without invoice-create access, on the project page', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $project = Project::factory()->create(['owner_id' => $support->id]);

    $html = $this->actingAs($support)->get(route('projects.show', $project))->assertOk()->getContent();

    expect($html)->not->toContain('Log Invoice');
});

it('hides a project belonging to an inactive client from the project list', function () {
    $activeClient = Customer::factory()->create(['status' => 'active', 'company_name' => 'Active Co']);
    $inactiveClient = Customer::factory()->create(['status' => 'inactive', 'company_name' => 'Inactive Co']);
    Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $activeClient->id, 'name' => 'Active project']);
    Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $inactiveClient->id, 'name' => 'Inactive-client project']);

    $this->actingAs($this->manager)->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Active project')
        ->assertDontSee('Inactive-client project');
});

it('still shows a project whose client was soft-deleted (Client removed), distinct from an Inactive-status client', function () {
    $deletedClient = Customer::factory()->create(['company_name' => 'Deleted Co']);
    Project::factory()->create(['owner_id' => $this->manager->id, 'customer_id' => $deletedClient->id, 'name' => 'Orphaned project']);
    // withoutEvents: deleting a client normally cascades and hard-deletes
    // its projects too — this simulates the orphan edge case the "Client
    // removed" convention exists for (see OrphanedCustomerTest), not a
    // real delete of this project.
    Customer::withoutEvents(fn () => $deletedClient->delete());

    $this->actingAs($this->manager)->get(route('projects.index'))
        ->assertOk()->assertSee('Orphaned project');
});

it('lets a manager delete a project but blocks a sales rep who only owns it', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertForbidden();
    expect(Project::find($project->id))->not->toBeNull();

    $this->actingAs($this->manager)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));
    expect(Project::find($project->id))->toBeNull();
});

it('returns null completion percentage for a project with no tasks', function () {
    $project = Project::factory()->create(['owner_id' => $this->manager->id]);

    expect($project->completionPercentage())->toBeNull();
});

it('computes completion percentage from the ratio of Done tasks', function () {
    $project = Project::factory()->create(['owner_id' => $this->manager->id]);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);
    Task::factory()->count(2)->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);

    expect($project->completionPercentage())->toBe(50);
});
