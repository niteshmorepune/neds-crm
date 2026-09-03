<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\OverdueInvoiceFollowUpSource;

function overdueInvoiceFollowUpSource(): OverdueInvoiceFollowUpSource
{
    return app(OverdueInvoiceFollowUpSource::class);
}

it('returns null for a non-Sales user', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create(['owner_id' => $support->id]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    expect(overdueInvoiceFollowUpSource()->next($support))->toBeNull();
});

it("prompts the client's owning Sales rep about an overdue invoice", function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id, 'company_name' => 'Curamind']);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue, 'invoice_number' => 'NEDS/2026-27/0042']);

    $action = overdueInvoiceFollowUpSource()->next($sales);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($invoice->id);
    expect($action->title)->toBe("Follow up: Curamind's invoice is overdue");
    expect($action->body)->toContain('NEDS/2026-27/0042');
    expect($action->actionUrl)->toBe(route('invoices.show', $invoice));
});

it('does not prompt an invoice that is not Overdue', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Sent]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Paid]);

    expect(overdueInvoiceFollowUpSource()->next($sales))->toBeNull();
});

it("does not surface another rep's client invoice", function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $other = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $other->id]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    expect(overdueInvoiceFollowUpSource()->next($sales))->toBeNull();
});

it('picks the earliest-due overdue invoice when more than one qualifies', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue, 'due_date' => now()->subDays(2)]);
    $earlier = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue, 'due_date' => now()->subDays(10)]);

    expect(overdueInvoiceFollowUpSource()->next($sales)?->subjectId)->toBe($earlier->id);
});

it('excludes a snoozed invoice but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'overdue_invoice_follow_up',
        'subject_type' => Invoice::class,
        'subject_id' => $invoice->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(overdueInvoiceFollowUpSource()->next($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(overdueInvoiceFollowUpSource()->next($sales)?->subjectId)->toBe($invoice->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $invoice = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Overdue]);

    overdueInvoiceFollowUpSource()->complete($sales, $invoice->id);
})->throws(RuntimeException::class);
