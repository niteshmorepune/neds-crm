<?php

use App\Enums\InvoiceStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\NextActionSnooze;
use App\Models\Quotation;
use App\Models\User;
use App\Services\NextAction\QuotationAcceptedNotConvertedSource;

function quotationAcceptedNotConvertedSource(): QuotationAcceptedNotConvertedSource
{
    return app(QuotationAcceptedNotConvertedSource::class);
}

function staleAcceptedQuotation(array $overrides = []): Quotation
{
    $quotation = Quotation::factory()->create(array_merge([
        'status' => QuotationStatus::Accepted,
    ], $overrides));

    $quotation->forceFill(['updated_at' => now()->subDays(4)])->saveQuietly();

    return $quotation;
}

it('returns null for a Sales user', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    staleAcceptedQuotation(['customer_id' => $customer->id]);

    expect(quotationAcceptedNotConvertedSource()->next($sales))->toBeNull();
});

it('prompts an Accounts user to convert a stale accepted quotation', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create(['company_name' => 'Curamind']);
    $quotation = staleAcceptedQuotation(['customer_id' => $customer->id]);

    $action = quotationAcceptedNotConvertedSource()->next($accounts);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($quotation->id);
    expect($action->title)->toBe('Convert accepted quotation: Curamind');
    expect($action->actionUrl)->toBe(route('quotations.show', $quotation));
});

it('also prompts Admin and Manager', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $customer = Customer::factory()->create();
    $quotation = staleAcceptedQuotation(['customer_id' => $customer->id]);

    expect(quotationAcceptedNotConvertedSource()->next($admin)?->subjectId)->toBe($quotation->id);
    expect(quotationAcceptedNotConvertedSource()->next($manager)?->subjectId)->toBe($quotation->id);
});

it('does not prompt before 3 days have passed', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    Quotation::factory()->create(['customer_id' => $customer->id, 'status' => QuotationStatus::Accepted]);

    expect(quotationAcceptedNotConvertedSource()->next($accounts))->toBeNull();
});

it('does not prompt a quotation that is not Accepted', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    staleAcceptedQuotation(['customer_id' => $customer->id, 'status' => QuotationStatus::Sent]);

    expect(quotationAcceptedNotConvertedSource()->next($accounts))->toBeNull();
});

it('does not prompt an accepted quotation that already has an invoice', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $quotation = staleAcceptedQuotation(['customer_id' => $customer->id]);
    Invoice::factory()->create(['customer_id' => $customer->id, 'quotation_id' => $quotation->id, 'status' => InvoiceStatus::Draft]);

    expect(quotationAcceptedNotConvertedSource()->next($accounts))->toBeNull();
});

it('picks the oldest-accepted quotation when more than one qualifies', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    staleAcceptedQuotation(['customer_id' => $customer->id]);
    $older = staleAcceptedQuotation(['customer_id' => $customer->id]);
    $older->forceFill(['updated_at' => now()->subDays(10)])->saveQuietly();

    expect(quotationAcceptedNotConvertedSource()->next($accounts)?->subjectId)->toBe($older->id);
});

it('excludes a snoozed quotation but includes it again once the snooze expires', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $quotation = staleAcceptedQuotation(['customer_id' => $customer->id]);

    NextActionSnooze::create([
        'user_id' => $accounts->id,
        'source_key' => 'quotation_accepted_not_converted',
        'subject_type' => Quotation::class,
        'subject_id' => $quotation->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(quotationAcceptedNotConvertedSource()->next($accounts))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(quotationAcceptedNotConvertedSource()->next($accounts)?->subjectId)->toBe($quotation->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create();
    $quotation = staleAcceptedQuotation(['customer_id' => $customer->id]);

    quotationAcceptedNotConvertedSource()->complete($accounts, $quotation->id);
})->throws(RuntimeException::class);
