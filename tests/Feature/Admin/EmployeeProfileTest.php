<?php

use App\Enums\TaskStatus;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Customer;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('lets an admin and a manager reach the employee list, but forbids other roles', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($this->admin)->get(route('employees.index'))->assertOk();
    $this->actingAs($manager)->get(route('employees.index'))->assertOk();
    $this->actingAs($sales)->get(route('employees.index'))->assertForbidden();
});

it('lets a manager reach an individual employee 360 page', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $staff = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($manager)->get(route('employees.show', $staff))
        ->assertOk()
        ->assertSee($staff->name);
});

it('does not list an inactive employee on the index', function () {
    $active = User::factory()->role(UserRole::Support)->create(['is_active' => true]);
    $inactive = User::factory()->role(UserRole::Support)->create(['is_active' => false]);

    $this->actingAs($this->admin)->get(route('employees.index'))
        ->assertSee($active->name)
        ->assertDontSee($inactive->name);
});

it('shows this months task workload counts on the 360 page', function () {
    $staff = User::factory()->role(UserRole::Support)->create();
    Task::factory()->count(2)->create(['assignee_id' => $staff->id, 'status' => TaskStatus::Done->value]);
    Task::factory()->create(['assignee_id' => $staff->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->addWeek()]);
    Task::factory()->create(['assignee_id' => $staff->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->subDay()]);

    $response = $this->actingAs($this->admin)->get(route('employees.show', $staff));

    $response->assertOk();
    expect($response->viewData('workload'))->toBe(['total' => 4, 'pending' => 2, 'overdue' => 1]);
});

it('shows tickets assigned to the employee with their client and status', function () {
    $staff = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create(['company_name' => 'Shridha Biotech']);
    $ticket = Ticket::factory()->create(['assignee_id' => $staff->id, 'customer_id' => $customer->id, 'status' => TicketStatus::Open->value]);

    $this->actingAs($this->admin)->get(route('employees.show', $staff))
        ->assertOk()
        ->assertSee('Shridha Biotech')
        ->assertSee($ticket->subject);
});

it('excludes a soft-deleted ticket from the employees assigned list, since deleting its client cascades to it too', function () {
    $staff = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create();
    $ticket = Ticket::factory()->create(['assignee_id' => $staff->id, 'customer_id' => $customer->id]);
    $customer->delete(); // cascades to soft-delete the ticket as well (Customer::booted())

    $response = $this->actingAs($this->admin)->get(route('employees.show', $staff));

    expect($response->viewData('ticketCounts'))->toBe(['open' => 0, 'total' => 0]);
});

it('shows recent attendance entries newest first', function () {
    $staff = User::factory()->role(UserRole::Support)->create();
    Attendance::factory()->create(['user_id' => $staff->id, 'date' => now()->subDays(2)->toDateString(), 'status' => 'present']);
    Attendance::factory()->create(['user_id' => $staff->id, 'date' => now()->subDay()->toDateString(), 'status' => 'absent']);

    $response = $this->actingAs($this->admin)->get(route('employees.show', $staff));

    $dates = $response->viewData('attendance')->pluck('date')->map->toDateString()->all();
    expect($dates)->toBe([now()->subDay()->toDateString(), now()->subDays(2)->toDateString()]);
});

it('shows Manager Notes on the 360 page and lets a manager add one', function () {
    $manager = User::factory()->role(UserRole::Manager)->create();
    $staff = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($manager)->get(route('employees.show', $staff))
        ->assertOk()
        ->assertSee('Manager Notes')
        ->assertSeeLivewire('record-notes');
});

it('forbids a non admin/manager from viewing an employee 360 page even with the direct URL', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $other = User::factory()->role(UserRole::Support)->create();

    $this->actingAs($support)->get(route('employees.show', $other))->assertForbidden();
});
