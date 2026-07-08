<?php

use App\Actions\CreateProjectFromDeal;
use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Project;
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

it('lets a manager delete a project but blocks a sales rep who only owns it', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $project = Project::factory()->create(['owner_id' => $owner->id]);

    $this->actingAs($owner)->delete(route('projects.destroy', $project))->assertForbidden();
    expect(Project::find($project->id))->not->toBeNull();

    $this->actingAs($this->manager)->delete(route('projects.destroy', $project))->assertRedirect(route('projects.index'));
    expect(Project::find($project->id))->toBeNull();
});
