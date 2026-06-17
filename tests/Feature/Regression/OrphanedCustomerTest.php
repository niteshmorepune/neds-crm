<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;

/**
 * Regression coverage for the 2026-06-16 incident: a customer with live
 * deals/projects/quotations/invoices/tickets got soft-deleted, and every
 * index/show page that did $record->customer->company_name (no null check)
 * 500'd for the whole team. Customer deletion now cascades (see CustomerCrudTest),
 * but these tests guard that views render gracefully if an orphan ever exists
 * (e.g. data fixed by hand, or a future code path that misses the cascade).
 * We use Customer::withoutEvents() to soft-delete the customer without triggering
 * the cascade, intentionally creating orphaned records.
 */
beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

it('renders deals board, projects, quotations, invoices, tickets and receivables when a customer is soft-deleted', function () {
    $deal = Deal::factory()->create();
    Customer::withoutEvents(fn () => $deal->customer->delete());

    $project = Project::factory()->create();
    Customer::withoutEvents(fn () => $project->customer->delete());

    $quotation = Quotation::factory()->create();
    Customer::withoutEvents(fn () => $quotation->customer->delete());

    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
    Customer::withoutEvents(fn () => $invoice->customer->delete());

    $ticket = Ticket::factory()->create();
    Customer::withoutEvents(fn () => $ticket->customer->delete());

    $this->actingAs($this->admin)->get(route('deals.index'))->assertOk();
    $this->actingAs($this->admin)->get(route('projects.index'))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('quotations.index'))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('invoices.index'))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('tickets.index'))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('reports.receivables'))->assertOk();

    $this->actingAs($this->admin)->get(route('deals.show', $deal))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('projects.show', $project))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('quotations.show', $quotation))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('invoices.show', $invoice))->assertOk()->assertSee('Client removed');
    $this->actingAs($this->admin)->get(route('tickets.show', $ticket))->assertOk()->assertSee('Client removed');
});

it('excludes an invoice with a deleted customer from the receivables report instead of crashing', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
    Customer::withoutEvents(fn () => $invoice->customer->delete());

    $this->actingAs($this->admin)
        ->get(route('reports.receivables'))
        ->assertOk()
        ->assertDontSee($invoice->invoice_number);
});
