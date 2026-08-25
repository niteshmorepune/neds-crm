<?php

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Service;
use App\Models\ServiceAssignment;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('assigns a responsible employee to a client service', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    $employee = User::factory()->role(UserRole::Support)->create(['name' => 'Priya Support']);

    $this->actingAs($this->manager)->post(route('service-assignments.store', $customer), [
        'service_id' => $service->id, 'user_id' => $employee->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('service_assignments', [
        'customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => $employee->id,
    ]);
});

it('reassigning the same client/service pair updates the existing row, not a second one', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    $this->actingAs($this->manager)->post(route('service-assignments.store', $customer), [
        'service_id' => $service->id, 'user_id' => $first->id,
    ]);
    $this->actingAs($this->manager)->post(route('service-assignments.store', $customer), [
        'service_id' => $service->id, 'user_id' => $second->id,
    ]);

    expect(ServiceAssignment::where('customer_id', $customer->id)->where('service_id', $service->id)->count())->toBe(1);
    $this->assertDatabaseHas('service_assignments', [
        'customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => $second->id,
    ]);
});

it('unassigns via destroy', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    $assignment = ServiceAssignment::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => User::factory()->create()->id]);

    $this->actingAs($this->manager)->delete(route('service-assignments.destroy', $assignment))->assertRedirect();

    $this->assertDatabaseMissing('service_assignments', ['id' => $assignment->id]);
});

it('allows admin/manager/sales/support to manage assignments, blocks accounts/intern/telecaller', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();

    foreach ([UserRole::Admin, UserRole::Manager, UserRole::Sales, UserRole::Support] as $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->post(route('service-assignments.store', $customer), ['service_id' => $service->id, 'user_id' => User::factory()->create()->id])
            ->assertRedirect();
    }

    foreach ([UserRole::Accounts, UserRole::Intern, UserRole::Telecaller] as $role) {
        $this->actingAs(User::factory()->role($role)->create())
            ->post(route('service-assignments.store', $customer), ['service_id' => $service->id, 'user_id' => User::factory()->create()->id])
            ->assertForbidden();
    }
});

it('shows a live project\'s own team on the Services tab even when a service assignment also exists', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['name' => 'SEO']);
    $projectOwner = User::factory()->create(['name' => 'Project Owner Person']);
    $assignedEmployee = User::factory()->create(['name' => 'Fallback Assignee Person']);

    Project::factory()->create([
        'customer_id' => $customer->id, 'service_id' => $service->id,
        'owner_id' => $projectOwner->id, 'status' => ProjectStatus::Active->value,
    ]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);
    ServiceAssignment::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => $assignedEmployee->id]);

    $this->actingAs($this->manager)->get(route('clients.show', $customer))->assertOk()
        ->assertSee('Project Owner Person')
        ->assertDontSee('Fallback Assignee Person');
});

it('shows the service assignment when the service has no live project', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create(['name' => 'GMB']);
    $assignedEmployee = User::factory()->create(['name' => 'Retainer Assignee Person']);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);
    ServiceAssignment::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => $assignedEmployee->id]);

    $this->actingAs($this->manager)->get(route('clients.show', $customer))->assertOk()
        ->assertSee('Retainer Assignee Person');
});

it('falls back to the assignment once a service\'s only project is Completed', function () {
    $customer = Customer::factory()->create();
    $service = Service::factory()->create();
    $projectOwner = User::factory()->create(['name' => 'Completed Project Owner']);
    $assignedEmployee = User::factory()->create(['name' => 'Post Completion Assignee']);

    Project::factory()->create([
        'customer_id' => $customer->id, 'service_id' => $service->id,
        'owner_id' => $projectOwner->id, 'status' => ProjectStatus::Completed->value,
    ]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'service_id' => $service->id]);
    ServiceAssignment::create(['customer_id' => $customer->id, 'service_id' => $service->id, 'user_id' => $assignedEmployee->id]);

    $this->actingAs($this->manager)->get(route('clients.show', $customer))->assertOk()
        ->assertSee('Post Completion Assignee');
});
