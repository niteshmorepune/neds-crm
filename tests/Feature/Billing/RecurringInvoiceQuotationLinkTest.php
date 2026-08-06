<?php

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Livewire\QuotationBuilder;
use App\Livewire\RecurringInvoiceBuilder;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\RecurringInvoice;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function mixedAcceptedQuotation(): Quotation
{
    $quotation = Quotation::factory()->create([
        'status' => QuotationStatus::Accepted, 'place_of_supply_state_code' => '27',
    ]);
    $quotation->items()->create([
        'description' => 'Website design & development', 'sac_code' => '998314',
        'quantity' => 1, 'rate' => 4500000, 'gst_rate' => 18, 'amount' => 4500000,
        'is_recurring' => false,
    ]);
    $quotation->items()->create([
        'description' => 'Paid ads management', 'sac_code' => '998363',
        'quantity' => 1, 'rate' => 1350000, 'gst_rate' => 18, 'amount' => 1350000,
        'is_recurring' => true,
    ]);
    $quotation->items()->create([
        'description' => 'Lead tracking & reporting', 'sac_code' => '998363',
        'quantity' => 1, 'rate' => 450000, 'gst_rate' => 18, 'amount' => 450000,
        'is_recurring' => true,
    ]);
    $quotation->refresh()->recalculateTotals();

    return $quotation->refresh();
}

it('persists the recurring flag per line item from the QuotationBuilder', function () {
    $customer = Customer::factory()->create(['state_code' => '27']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->set('customer_id', $customer->id)
        ->set('items', [
            ['description' => 'Website build', 'sac_code' => '', 'quantity' => '1', 'rate' => '45000', 'gst_rate' => '18', 'is_recurring' => false],
            ['description' => 'Ads management', 'sac_code' => '', 'quantity' => '1', 'rate' => '13500', 'gst_rate' => '18', 'is_recurring' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::first();
    expect($quotation->items()->where('is_recurring', false)->first()->description)->toBe('Website build')
        ->and($quotation->items()->where('is_recurring', true)->first()->description)->toBe('Ads management')
        ->and($quotation->hasRecurringItems())->toBeTrue();
});

it('reports no recurring items when a quotation is entirely one-time', function () {
    $quotation = mixedAcceptedQuotation();
    $quotation->items()->update(['is_recurring' => false]);

    expect($quotation->fresh()->hasRecurringItems())->toBeFalse();
});

it('prefills the RecurringInvoiceBuilder from an accepted quotation, carrying only the recurring-flagged items', function () {
    $quotation = mixedAcceptedQuotation();

    $component = Livewire::actingAs($this->admin)
        ->test(RecurringInvoiceBuilder::class, ['quotation_id' => $quotation->id])
        ->assertSet('quotationId', $quotation->id)
        ->assertSet('customer_id', $quotation->customer_id)
        ->assertSet('items.0.description', 'Paid ads management')
        ->assertSet('items.1.description', 'Lead tracking & reporting');

    expect($component->get('items'))->toHaveCount(2);
});

it('saves a quotation_id-linked recurring invoice, traceable back to the quotation', function () {
    $quotation = mixedAcceptedQuotation();

    Livewire::actingAs($this->admin)
        ->test(RecurringInvoiceBuilder::class, ['quotation_id' => $quotation->id])
        ->set('service_id', null)
        ->call('save');

    $recurring = RecurringInvoice::firstWhere('customer_id', $quotation->customer_id);
    expect($recurring)->not->toBeNull()
        ->and($recurring->quotation_id)->toBe($quotation->id)
        ->and($recurring->items)->toHaveCount(2);

    expect($quotation->recurringInvoices()->count())->toBe(1);
});

it('shows the "Create recurring invoice" action only on an accepted quotation with recurring items', function () {
    $withRecurring = mixedAcceptedQuotation();
    $this->actingAs($this->admin)->get(route('quotations.show', $withRecurring))
        ->assertOk()->assertSee('Create recurring invoice');

    $oneTimeOnly = mixedAcceptedQuotation();
    $oneTimeOnly->items()->update(['is_recurring' => false]);
    $this->actingAs($this->admin)->get(route('quotations.show', $oneTimeOnly))
        ->assertOk()->assertDontSee('Create recurring invoice');

    $stillDraft = Quotation::factory()->create(['status' => QuotationStatus::Draft]);
    $stillDraft->items()->create([
        'description' => 'Retainer', 'quantity' => 1, 'rate' => 10000, 'gst_rate' => 18,
        'amount' => 10000, 'is_recurring' => true,
    ]);
    $this->actingAs($this->admin)->get(route('quotations.show', $stillDraft))
        ->assertOk()->assertDontSee('Create recurring invoice');
});

it('lists recurring invoices generated from a quotation on its show page', function () {
    $quotation = mixedAcceptedQuotation();
    $recurring = RecurringInvoice::factory()->create([
        'customer_id' => $quotation->customer_id,
        'quotation_id' => $quotation->id,
    ]);

    $this->actingAs($this->admin)->get(route('quotations.show', $quotation))
        ->assertOk()->assertSee(route('recurring-invoices.show', $recurring), false);
});
