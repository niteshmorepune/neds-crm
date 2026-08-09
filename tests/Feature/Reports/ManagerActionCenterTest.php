<?php

use App\Enums\InvoiceStatus;
use App\Enums\RecurringFrequency;
use App\Enums\TaskStatus;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\FollowUpReminder;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->manager = User::factory()->role(UserRole::Manager)->create();
});

it('lets admin and manager reach the action center, but forbids other roles', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();

    $this->actingAs($admin)->get(route('manager-action-center.index'))->assertOk();
    $this->actingAs($this->manager)->get(route('manager-action-center.index'))->assertOk();
    $this->actingAs($sales)->get(route('manager-action-center.index'))->assertForbidden();
});

it('counts an overdue task assigned to anyone on the team, not just the viewer', function () {
    $someoneElse = User::factory()->role(UserRole::Support)->create();
    Task::factory()->create(['assignee_id' => $someoneElse->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->subDay()]);
    Task::factory()->create(['assignee_id' => $someoneElse->id, 'status' => TaskStatus::Todo->value, 'due_date' => now()->addDay()]);
    Task::factory()->create(['assignee_id' => $someoneElse->id, 'status' => TaskStatus::Done->value, 'due_date' => now()->subDay()]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $overdue = $response->viewData('signals')->firstWhere('key', 'overdue_tasks');
    expect($overdue['count'])->toBe(1);
});

it('counts flagged clients from Client Radar', function () {
    $customer = Customer::factory()->create(['status' => 'active', 'created_at' => now()->subDays(60)]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue->value, 'due_date' => now()->subDays(20)]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $atRisk = $response->viewData('signals')->firstWhere('key', 'at_risk_clients');
    expect($atRisk['count'])->toBeGreaterThanOrEqual(1);
});

it('counts overdue invoices but not draft or sent ones', function () {
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue->value]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Draft->value]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Paid->value]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $overdueInvoices = $response->viewData('signals')->firstWhere('key', 'overdue_invoices');
    expect($overdueInvoices['count'])->toBe(1);
});

it('counts a ticket whose SLA has genuinely breached, not one merely at risk', function () {
    $customer = Customer::factory()->create();
    Ticket::factory()->create(['customer_id' => $customer->id, 'status' => TicketStatus::Open->value, 'priority' => TicketPriority::High->value, 'sla_due_at' => now()->subHour()]);
    Ticket::factory()->create(['customer_id' => $customer->id, 'status' => TicketStatus::Open->value, 'priority' => TicketPriority::Low->value, 'sla_due_at' => now()->addHours(2)]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $breaches = $response->viewData('signals')->firstWhere('key', 'sla_breaches');
    expect($breaches['count'])->toBe(1);
});

it('counts escalated tickets, not merely SLA-breached ones', function () {
    $customer = Customer::factory()->create();
    Ticket::factory()->create(['customer_id' => $customer->id, 'escalated_at' => now(), 'escalated_by' => $this->manager->id]);
    Ticket::factory()->create(['customer_id' => $customer->id, 'status' => TicketStatus::Open->value, 'sla_due_at' => now()->subHour()]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $escalated = $response->viewData('signals')->firstWhere('key', 'escalated_tickets');
    expect($escalated['count'])->toBe(1);
});

it('counts contract renewals due within 30 days using the same scope as the Contract Renewal Dashboard', function () {
    $customer = Customer::factory()->create();
    $template = RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'is_active' => true, 'end_date' => now()->addDays(10), 'frequency' => RecurringFrequency::Monthly->value]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'is_active' => true, 'end_date' => now()->addDays(60)]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $renewals = $response->viewData('signals')->firstWhere('key', 'renewals_due');
    expect($renewals['count'])->toBe(1);
});

it('lists pending follow-ups inline, showing Client removed for one whose customer was soft-deleted', function () {
    $customer = Customer::factory()->create(['company_name' => 'Prakash Electrical']);
    $reminder = FollowUpReminder::factory()->create(['customer_id' => $customer->id, 'user_id' => $this->manager->id, 'next_action' => 'Send renewal quote']);

    $orphanCustomer = Customer::factory()->create();
    $orphanReminder = FollowUpReminder::factory()->create(['customer_id' => $orphanCustomer->id, 'user_id' => $this->manager->id, 'next_action' => 'Chase invoice']);
    $orphanCustomer->delete();

    $completed = FollowUpReminder::factory()->completed()->create(['customer_id' => $customer->id, 'user_id' => $this->manager->id]);

    $response = $this->actingAs($this->manager)->get(route('manager-action-center.index'));

    $response->assertSee('Prakash Electrical')
        ->assertSee('Send renewal quote')
        ->assertSee('Chase invoice')
        ->assertSee('Client removed');

    expect($response->viewData('followUpCount'))->toBe(2);
});
