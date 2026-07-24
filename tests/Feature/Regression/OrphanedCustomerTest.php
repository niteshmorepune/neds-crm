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
use App\Services\DashboardMetrics;
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

it('shows an invoice with a deleted customer on the receivables report as "Client removed", not hidden', function () {
    // 2026-07-24 incident: an earlier fix excluded these entirely to avoid
    // the crash below, but that also silently hid real unpaid money from
    // both this report and the Accounts dashboard tile, and let the two
    // totals drift out of sync with each other. The correct fix is to show
    // the row (same "Client removed" fallback invoices/index.blade.php
    // already uses), not exclude it.
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent, 'total' => 500000, 'amount_paid' => 0]);
    Customer::withoutEvents(fn () => $invoice->customer->delete());

    $this->actingAs($this->admin)
        ->get(route('reports.receivables'))
        ->assertOk()
        ->assertSee('Client removed')
        ->assertSee('₹5,000.00', false);
});

it('keeps the Accounts dashboard outstanding tile in sync with the Receivables Report total', function () {
    // 2026-07-24 incident: these two used separate, subtly different
    // queries (one excluded invoices from deleted customers, one didn't;
    // one excluded Draft, one didn't) and silently disagreed in
    // production. Both must now be built on the same
    // CollectionsMetrics::outstandingInvoicesQuery().
    Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'total' => 300000, 'amount_paid' => 0]);
    $orphaned = Invoice::factory()->create(['status' => InvoiceStatus::Overdue, 'total' => 500000, 'amount_paid' => 0]);
    Customer::withoutEvents(fn () => $orphaned->customer->delete());

    $dashboard = app(DashboardMetrics::class)->accountsStats();
    $receivablesTotal = $this->actingAs($this->admin)->get(route('reports.receivables'))->viewData('total');

    expect($dashboard['outstanding'])->toBe(800000)
        ->and($receivablesTotal)->toBe(800000);
});
