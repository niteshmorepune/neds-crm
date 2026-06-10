<?php

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Livewire\QuotationBuilder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

function quotationWithLine(array $attributes = []): Quotation
{
    $quotation = Quotation::factory()->create(array_merge([
        'place_of_supply_state_code' => '27',
    ], $attributes));

    $quotation->items()->create([
        'description' => 'SEO retainer',
        'sac_code' => '998361',
        'quantity' => 1,
        'rate' => 100000, // ₹1000
        'gst_rate' => 18,
        'amount' => 100000,
    ]);
    $quotation->refresh()->recalculateTotals();

    return $quotation->refresh();
}

it('builds a quotation with live GST totals and assigns a number', function () {
    $customer = Customer::factory()->create(['state_code' => '27']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->set('customer_id', $customer->id)
        ->set('items', [[
            'description' => 'SEO retainer', 'sac_code' => '998361',
            'quantity' => '1', 'rate' => '1000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::first();

    expect($quotation->total)->toBe(118000)
        ->and($quotation->cgst_total)->toBe(9000)
        ->and($quotation->sgst_total)->toBe(9000)
        ->and($quotation->number)->not->toBeNull()
        ->and($quotation->items()->count())->toBe(1);
});

it('allows valid status transitions and blocks invalid ones', function () {
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)->post(route('quotations.status', $quotation), ['status' => 'sent']);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);

    $this->actingAs($this->admin)->post(route('quotations.status', $quotation), ['status' => 'accepted']);
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Accepted);

    // draft -> accepted is not allowed
    $draft = quotationWithLine();
    $this->actingAs($this->admin)
        ->post(route('quotations.status', $draft), ['status' => 'accepted'])
        ->assertSessionHasErrors('status');
    expect($draft->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('converts an accepted quotation into an invoice with copied items and totals', function () {
    $quotation = quotationWithLine(['status' => QuotationStatus::Accepted]);

    $this->actingAs($this->admin)->post(route('quotations.convert', $quotation))->assertRedirect();

    $invoice = Invoice::firstWhere('quotation_id', $quotation->id);

    expect($invoice)->not->toBeNull()
        ->and($invoice->total)->toBe(118000)
        ->and($invoice->items()->count())->toBe(1)
        ->and($invoice->invoice_number)->toStartWith('NEDS/');
});

it('refuses to convert a quotation that is not accepted', function () {
    $quotation = quotationWithLine(); // draft

    $this->actingAs($this->admin)->post(route('quotations.convert', $quotation))->assertSessionHasErrors('convert');
    expect(Invoice::count())->toBe(0);
});

it('renders quotation index, create and show pages', function () {
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)->get(route('quotations.index'))->assertOk()->assertSee('Quotations');
    $this->actingAs($this->admin)->get(route('quotations.create'))->assertOk()->assertSee('Line items');
    $this->actingAs($this->admin)->get(route('quotations.show', $quotation))->assertOk()->assertSee($quotation->number);
});
