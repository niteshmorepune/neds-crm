<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NextActionSnooze;
use App\Models\User;
use App\Services\NextAction\DraftInvoiceUnsentSource;

function draftInvoiceUnsentSource(): DraftInvoiceUnsentSource
{
    return app(DraftInvoiceUnsentSource::class);
}

function staleDraftInvoice(array $overrides = []): Invoice
{
    $invoice = Invoice::factory()->create(array_merge([
        'status' => InvoiceStatus::Draft,
    ], $overrides));

    $invoice->forceFill(['created_at' => now()->subDays(4)])->saveQuietly();

    return $invoice;
}

it('returns null for a Sales user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    staleDraftInvoice(['customer_id' => $customer->id]);

    expect(draftInvoiceUnsentSource()->next($sales))->toBeNull();
});

it('prompts an Accounts user to price and send a stale draft invoice', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    $invoice = staleDraftInvoice(['customer_id' => $customer->id]);

    $action = draftInvoiceUnsentSource()->next($accounts);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($invoice->id);
    expect($action->title)->toBe('Price & send draft invoice: Curamind');
    expect($action->actionUrl)->toBe(route('invoices.show', $invoice));
});

it('also prompts Admin and Manager, a wider audience than the original notification', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $invoice = staleDraftInvoice(['customer_id' => $customer->id]);

    expect(draftInvoiceUnsentSource()->next($admin)?->subjectId)->toBe($invoice->id);
    expect(draftInvoiceUnsentSource()->next($manager)?->subjectId)->toBe($invoice->id);
});

it('does not prompt before 3 days have passed', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Draft, 'created_at' => now()->subDay()]);

    expect(draftInvoiceUnsentSource()->next($accounts))->toBeNull();
});

it('does not prompt an invoice that is not Draft', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    staleDraftInvoice(['customer_id' => $customer->id, 'status' => InvoiceStatus::Sent]);

    expect(draftInvoiceUnsentSource()->next($accounts))->toBeNull();
});

it('picks the oldest draft invoice when more than one qualifies', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    staleDraftInvoice(['customer_id' => $customer->id]);
    $older = Invoice::factory()->create(['customer_id' => $customer->id, 'status' => InvoiceStatus::Draft]);
    $older->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

    expect(draftInvoiceUnsentSource()->next($accounts)?->subjectId)->toBe($older->id);
});

it('excludes a snoozed invoice but includes it again once the snooze expires', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $invoice = staleDraftInvoice(['customer_id' => $customer->id]);

    NextActionSnooze::create([
        'user_id' => $accounts->id,
        'source_key' => 'draft_invoice_unsent',
        'subject_type' => Invoice::class,
        'subject_id' => $invoice->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(draftInvoiceUnsentSource()->next($accounts))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(draftInvoiceUnsentSource()->next($accounts)?->subjectId)->toBe($invoice->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $invoice = staleDraftInvoice(['customer_id' => $customer->id]);

    draftInvoiceUnsentSource()->complete($accounts, $invoice->id);
})->throws(RuntimeException::class);
