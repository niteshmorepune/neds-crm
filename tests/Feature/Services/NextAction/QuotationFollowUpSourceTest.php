<?php

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\NextActionSnooze;
use App\Models\Quotation;
use App\Models\User;
use App\Services\NextAction\QuotationFollowUpSource;

function quotationFollowUpSource(): QuotationFollowUpSource
{
    return app(QuotationFollowUpSource::class);
}

function sentQuotation(array $overrides = []): Quotation
{
    $quotation = Quotation::factory()->create(array_merge([
        'status' => QuotationStatus::Sent,
    ], $overrides));

    $quotation->forceFill(['updated_at' => now()->subDays(4)])->saveQuietly();

    return $quotation;
}

it('returns null for a non-Sales user', function () {
    $support = User::factory()->role(UserRole::Support)->create();
    $customer = Customer::factory()->create(['owner_id' => $support->id]);
    sentQuotation(['customer_id' => $customer->id]);

    expect(quotationFollowUpSource()->next($support))->toBeNull();
});

it('prompts a Sales rep to follow up on a customer-owned quotation stalled 3+ days at Sent', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id, 'company_name' => 'Curamind']);
    $quotation = sentQuotation(['customer_id' => $customer->id]);

    $action = quotationFollowUpSource()->next($sales);

    expect($action)->not->toBeNull();
    expect($action->subjectId)->toBe($quotation->id);
    expect($action->title)->toBe("Follow up: Curamind's quotation");
    expect($action->actionUrl)->toBe(route('quotations.show', $quotation));
});

it('prompts via the deal owner when the quotation came from a deal, not the customer owner', function () {
    $dealOwner = User::factory()->role(UserRole::Sales)->create();
    $customerOwner = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $customerOwner->id]);
    $deal = Deal::factory()->create(['customer_id' => $customer->id, 'owner_id' => $dealOwner->id]);
    $quotation = sentQuotation(['customer_id' => $customer->id, 'deal_id' => $deal->id]);

    expect(quotationFollowUpSource()->next($dealOwner)?->subjectId)->toBe($quotation->id);
    expect(quotationFollowUpSource()->next($customerOwner))->toBeNull();
});

it('does not prompt before 3 days have passed', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'status' => QuotationStatus::Sent]);
    $quotation->forceFill(['updated_at' => now()->subDays(1)])->saveQuietly();

    expect(quotationFollowUpSource()->next($sales))->toBeNull();
});

it('does not prompt a Draft or Accepted quotation', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    sentQuotation(['customer_id' => $customer->id, 'status' => QuotationStatus::Draft]);
    sentQuotation(['customer_id' => $customer->id, 'status' => QuotationStatus::Accepted]);

    expect(quotationFollowUpSource()->next($sales))->toBeNull();
});

it('excludes a snoozed quotation but includes it again once the snooze expires', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $quotation = sentQuotation(['customer_id' => $customer->id]);

    NextActionSnooze::create([
        'user_id' => $sales->id,
        'source_key' => 'quotation_follow_up',
        'subject_type' => Quotation::class,
        'subject_id' => $quotation->id,
        'snoozed_until' => now()->addMinutes(30),
    ]);

    expect(quotationFollowUpSource()->next($sales))->toBeNull();

    NextActionSnooze::query()->update(['snoozed_until' => now()->subMinute()]);

    expect(quotationFollowUpSource()->next($sales)?->subjectId)->toBe($quotation->id);
});

it('throws if complete() is ever called, since its prompt always links out instead', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $customer = Customer::factory()->create(['owner_id' => $sales->id]);
    $quotation = sentQuotation(['customer_id' => $customer->id]);

    quotationFollowUpSource()->complete($sales, $quotation->id);
})->throws(RuntimeException::class);
