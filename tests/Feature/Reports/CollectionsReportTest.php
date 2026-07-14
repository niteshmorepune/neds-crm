<?php

use App\Enums\InvoiceStatus;
use App\Enums\MilestoneStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\User;
use App\Services\CollectionsMetrics;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->metrics = app(CollectionsMetrics::class);
});

function recurringOverdueInvoice(Customer $customer, array $attributes = []): Invoice
{
    $recurring = RecurringInvoice::factory()->create(['customer_id' => $customer->id]);

    return Invoice::factory()->create(array_merge([
        'customer_id' => $customer->id,
        'recurring_invoice_id' => $recurring->id,
        'status' => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(10),
        'total' => 100000,
        'amount_paid' => 0,
    ], $attributes));
}

// --- Recurring overdue vs partial split -------------------------------------

it('counts a never-paid recurring overdue invoice as recurring_overdue, not partial', function () {
    $customer = Customer::factory()->create();
    recurringOverdueInvoice($customer);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['recurring_overdue_count'])->toBe(1)
        ->and($row['recurring_overdue_amount'])->toBe(100000)
        ->and($row['partial_count'])->toBe(0);
});

it('counts a partially-paid recurring invoice as partial even though MarkOverdueInvoices can promote it to Overdue status', function () {
    $customer = Customer::factory()->create();
    // amount_paid > 0 but status is still Overdue — the real-world state MarkOverdueInvoices
    // produces for a partially-paid invoice once its due date passes.
    recurringOverdueInvoice($customer, ['amount_paid' => 40000]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['recurring_overdue_count'])->toBe(0)
        ->and($row['partial_count'])->toBe(1)
        ->and($row['partial_amount'])->toBe(60000);
});

it('buckets a non-recurring fully-unpaid overdue invoice as other_unpaid, not recurring_overdue', function () {
    $customer = Customer::factory()->create();
    Invoice::factory()->create([
        'customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue,
        'due_date' => now()->subDays(5), 'total' => 50000, 'amount_paid' => 0,
    ]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['recurring_overdue_count'])->toBe(0)
        ->and($row['other_unpaid_count'])->toBe(1)
        ->and($row['other_unpaid_amount'])->toBe(50000)
        ->and($row['partial_count'])->toBe(0);
});

it('ignores paid, cancelled and draft invoices entirely', function () {
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Paid, 'total' => 50000, 'amount_paid' => 50000]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Cancelled, 'total' => 50000, 'amount_paid' => 0]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Draft, 'total' => 50000, 'amount_paid' => 0]);

    expect($this->metrics->clientHealth()->firstWhere('customer.id', $customer->id))->toBeNull();
});

// --- Days overdue / promise tracking ----------------------------------------

it('computes the oldest overdue days across a client\'s open invoices', function () {
    $customer = Customer::factory()->create();
    recurringOverdueInvoice($customer, ['due_date' => now()->subDays(5)]);
    recurringOverdueInvoice($customer, ['due_date' => now()->subDays(20)]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['oldest_overdue_days'])->toBe(20);
});

it('surfaces the soonest open payment promise and flags it broken once past', function () {
    $customer = Customer::factory()->create();
    recurringOverdueInvoice($customer, ['payment_promised_date' => now()->subDays(2)]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['payment_promised_date'])->not->toBeNull()
        ->and($row['promise_broken'])->toBeTrue();
});

it('does not flag a promise as broken while its date is still in the future', function () {
    $customer = Customer::factory()->create();
    recurringOverdueInvoice($customer, ['payment_promised_date' => now()->addDays(3)]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['promise_broken'])->toBeFalse();
});

// --- Project delivery + milestone readiness ---------------------------------

it('reports a project\'s task-completion percentage and excludes non-active projects', function () {
    $customer = Customer::factory()->create();
    $project = Project::factory()->create(['customer_id' => $customer->id, 'status' => ProjectStatus::Active]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Done]);
    Task::factory()->create(['project_id' => $project->id, 'status' => TaskStatus::Todo]);
    Project::factory()->create(['customer_id' => $customer->id, 'status' => ProjectStatus::Completed]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['projects'])->toHaveCount(1)
        ->and($row['projects'][0]['completion_percentage'])->toBe(50);
});

it('flags the next unbilled milestone as ready to invoice once marked Done', function () {
    $customer = Customer::factory()->create();
    $deal = Deal::factory()->create(['customer_id' => $customer->id]);
    $project = Project::factory()->create(['customer_id' => $customer->id, 'deal_id' => $deal->id, 'status' => ProjectStatus::Active]);
    $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'deal_id' => $deal->id, 'status' => QuotationStatus::Accepted]);
    $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 50, 'amount' => 50000, 'sort_order' => 0, 'status' => MilestoneStatus::Done]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['projects'][0]['milestone']['title'])->toBe('Advance')
        ->and($row['projects'][0]['milestone']['ready_to_invoice'])->toBeTrue();
});

it('does not flag a Pending milestone as ready to invoice', function () {
    $customer = Customer::factory()->create();
    $deal = Deal::factory()->create(['customer_id' => $customer->id]);
    Project::factory()->create(['customer_id' => $customer->id, 'deal_id' => $deal->id, 'status' => ProjectStatus::Active]);
    $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'deal_id' => $deal->id, 'status' => QuotationStatus::Accepted]);
    $quotation->milestones()->create(['title' => 'Advance', 'percentage' => 50, 'amount' => 50000, 'sort_order' => 0]);

    $row = $this->metrics->clientHealth()->firstWhere('customer.id', $customer->id);

    expect($row['projects'][0]['milestone']['ready_to_invoice'])->toBeFalse();
});

it('excludes a client with no overdue/partial invoices and no active projects', function () {
    $customer = Customer::factory()->create();

    expect($this->metrics->clientHealth()->firstWhere('customer.id', $customer->id))->toBeNull();
});

it('still includes a client with an active project but no billing issues', function () {
    $customer = Customer::factory()->create();
    Project::factory()->create(['customer_id' => $customer->id, 'status' => ProjectStatus::Active]);

    expect($this->metrics->clientHealth()->firstWhere('customer.id', $customer->id))->not->toBeNull();
});

// --- Partner / direct scoping ------------------------------------------------

it('scopes clientHealth to one referring partner', function () {
    $partner = Partner::factory()->create();
    $theirs = Customer::factory()->create(['referring_partner_id' => $partner->id]);
    $others = Customer::factory()->create();
    recurringOverdueInvoice($theirs);
    recurringOverdueInvoice($others);

    $rows = $this->metrics->clientHealth($partner->id);

    expect($rows->pluck('customer.id'))->toContain($theirs->id)
        ->not->toContain($others->id);
});

it('scopes clientHealth to direct clients only', function () {
    $partner = Partner::factory()->create();
    $referred = Customer::factory()->create(['referring_partner_id' => $partner->id]);
    $direct = Customer::factory()->create(['referring_partner_id' => null]);
    recurringOverdueInvoice($referred);
    recurringOverdueInvoice($direct);

    $rows = $this->metrics->clientHealth(null, directOnly: true);

    expect($rows->pluck('customer.id'))->toContain($direct->id)
        ->not->toContain($referred->id);
});

// --- Route access control ----------------------------------------------------

it('lets admin, manager and accounts view the collections report, blocking sales', function () {
    foreach ([UserRole::Admin, UserRole::Manager, UserRole::Accounts] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('reports.collections'))
            ->assertOk();
    }

    $this->actingAs(User::factory()->create(['role' => UserRole::Sales]))
        ->get(route('reports.collections'))
        ->assertForbidden();
});

it('filters the collections report by partner_id via the query string', function () {
    $partner = Partner::factory()->create(['name' => 'Acme Referrals']);
    $theirs = Customer::factory()->create(['company_name' => 'Their Client', 'referring_partner_id' => $partner->id]);
    $direct = Customer::factory()->create(['company_name' => 'Direct Client', 'referring_partner_id' => null]);
    recurringOverdueInvoice($theirs);
    recurringOverdueInvoice($direct);

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('reports.collections', ['partner_id' => $partner->id]))
        ->assertOk()->assertSee('Their Client')->assertDontSee('Direct Client');

    $this->actingAs($admin)->get(route('reports.collections', ['partner_id' => 'direct']))
        ->assertOk()->assertSee('Direct Client')->assertDontSee('Their Client');

    $this->actingAs($admin)->get(route('reports.collections'))
        ->assertOk()->assertSee('Their Client')->assertSee('Direct Client');
});
