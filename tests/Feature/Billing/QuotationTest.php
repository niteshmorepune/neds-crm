<?php

use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Livewire\QuotationBuilder;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\User;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
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

it('defaults the QuotationBuilder GST-exempt toggle from the selected client, and can still be overridden', function () {
    $exemptClient = Customer::factory()->create(['state_code' => '27', 'gst_exempt' => true]);
    $normalClient = Customer::factory()->create(['state_code' => '27', 'gst_exempt' => false]);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->set('customer_id', $exemptClient->id)
        ->assertSet('is_gst_exempt', true)
        ->set('customer_id', $normalClient->id)
        ->assertSet('is_gst_exempt', false)
        ->set('is_gst_exempt', true) // manual override survives the save
        ->set('items', [[
            'description' => 'SEO retainer', 'sac_code' => '998361',
            'quantity' => '1', 'rate' => '1000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::first();
    expect($quotation->is_gst_exempt)->toBeTrue()
        ->and($quotation->cgst_total)->toBe(0)
        ->and($quotation->total)->toBe(100000);
});

it('suggests line items grounded in the deal notes, leaving rate and GST % blank', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => '[{"description": "Hindi translation setup", "quantity": 1, "sac_code": null}]']],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 15],
        ]),
    ]);
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => $this->admin->id, 'body' => 'Client wants a Hindi translation of the whole site.']);
    $customer = Customer::factory()->create(['state_code' => '27']);

    $component = Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->set('customer_id', $customer->id)
        ->call('suggestItems')
        ->assertSet('lastSuggestedCount', 1)
        ->assertSet('items.0.description', 'Hindi translation setup')
        ->assertSet('items.0.rate', '')
        ->assertSet('items.0.gst_rate', '');

    // The same validation that already blocks a manually-blank rate blocks
    // a suggested one too — the guardrail is enforced by an existing rule,
    // not a new promise this feature has to keep on its own.
    $component->call('save')->assertHasErrors(['items.0.rate']);
    expect(Quotation::count())->toBe(0);
});

it('shows a friendly message and suggests nothing when the deal has no notes', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake();
    $deal = Deal::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->call('suggestItems')
        ->assertSet('lastSuggestedCount', 0)
        ->assertSee('Nothing specific to suggest yet');

    Http::assertNothingSent();
});

it('hides the suggest-items button when the quotation has no linked deal', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->assertDontSee('Suggest line items');
});

it('hides the suggest-items button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $deal = Deal::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->assertDontSee('Suggest line items');
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
        ->and($invoice->invoice_number)->toBeNull(); // Accounts assigns the number manually
});

it('carries the GST-exempt flag through when converting a quotation to an invoice', function () {
    $quotation = quotationWithLine(['status' => QuotationStatus::Accepted, 'is_gst_exempt' => true]);

    $this->actingAs($this->admin)->post(route('quotations.convert', $quotation))->assertRedirect();

    $invoice = Invoice::firstWhere('quotation_id', $quotation->id);

    expect($invoice->is_gst_exempt)->toBeTrue()
        ->and($invoice->cgst_total)->toBe(0)
        ->and($invoice->total)->toBe(100000);
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

it('shows a delete button on the quotation show page and deletes it', function () {
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)->get(route('quotations.show', $quotation))->assertOk()->assertSee('Delete');

    $this->actingAs($this->admin)->delete(route('quotations.destroy', $quotation))->assertRedirect(route('quotations.index'));
    expect(Quotation::find($quotation->id))->toBeNull();
});
