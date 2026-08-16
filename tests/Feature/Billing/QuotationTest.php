<?php

use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use App\Enums\UserRole;
use App\Livewire\QuotationBuilder;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\FollowUpReminder;
use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Quotation;
use App\Models\User;
use App\Notifications\QuotationNeedsApproval;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

it('drafts a scope of work grounded in deal notes into an editable field, never saving on its own', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'NEDS will deliver a full redesign of the client website, including a Hindi-language version of every page.']],
            'usage' => ['input_tokens' => 30, 'output_tokens' => 20],
        ]),
    ]);
    $deal = Deal::factory()->create();
    $deal->notes()->create(['user_id' => $this->admin->id, 'body' => 'Client wants a full site redesign plus a Hindi translation.']);
    $customer = Customer::factory()->create(['state_code' => '27']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->set('customer_id', $customer->id)
        ->call('draftScopeOfWork')
        ->assertSet('scope_of_work', 'NEDS will deliver a full redesign of the client website, including a Hindi-language version of every page.');

    // Drafting alone never persists anything — only an explicit save does.
    expect(Quotation::count())->toBe(0);
});

it('shows a friendly message and drafts nothing when the deal has no notes for scope of work', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);
    Http::fake();
    $deal = Deal::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->call('draftScopeOfWork')
        ->assertSet('scope_of_work', '')
        ->assertSee('Nothing to draft yet');

    Http::assertNothingSent();
});

it('hides the draft-scope-of-work button when the quotation has no linked deal', function () {
    config(['services.anthropic.enabled' => true, 'services.anthropic.key' => 'sk-test']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->assertDontSee('Draft scope of work');
});

it('hides the draft-scope-of-work button entirely when AI is disabled', function () {
    config(['services.anthropic.enabled' => false]);
    $deal = Deal::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class, ['deal_id' => $deal->id])
        ->assertDontSee('Draft scope of work');
});

it('allows valid status transitions and blocks invalid ones', function () {
    $quotation = quotationWithLine(['approval_status' => QuotationApprovalStatus::Approved]);

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

it('defaults a new quotation to pending approval and notifies admin/manager', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();

    $quotation = quotationWithLine();

    expect($quotation->approval_status)->toBe(QuotationApprovalStatus::Pending)
        ->and($quotation->needsApproval())->toBeTrue();

    Notification::assertSentTo($manager, QuotationNeedsApproval::class);
});

it('blocks sending or transitioning an unapproved quotation to Sent', function () {
    Mail::fake();
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)->post(route('quotations.send', $quotation))->assertSessionHasErrors('send');
    $this->actingAs($this->admin)->post(route('quotations.status', $quotation), ['status' => 'sent'])->assertSessionHasErrors('status');
    Mail::assertNothingSent();
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Draft);
});

it('lets a manager approve a quotation, unblocking send', function () {
    Mail::fake();
    $quotation = quotationWithLine(['customer_id' => Customer::factory()->create(['email' => 'client@x.test'])->id]);
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();

    $quotation->refresh();
    expect($quotation->approval_status)->toBe(QuotationApprovalStatus::Approved)
        ->and($quotation->approved_by)->toBe($manager->id)
        ->and($quotation->needsApproval())->toBeFalse();

    $this->actingAs($this->admin)->post(route('quotations.send', $quotation))->assertRedirect();
    expect($quotation->fresh()->status)->toBe(QuotationStatus::Sent);
});

it('creates a 3-day follow-up reminder for the sender when a quotation is sent', function () {
    Mail::fake();
    $customer = Customer::factory()->create(['email' => 'client@x.test', 'company_name' => 'Prajakta Digital']);
    $quotation = quotationWithLine(['customer_id' => $customer->id]);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();

    $now = now();
    $this->travelTo($now);
    $this->actingAs($this->admin)
        ->post(route('quotations.send', $quotation))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $reminder = FollowUpReminder::query()->where('customer_id', $customer->id)->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->user_id)->toBe($this->admin->id)
        ->and($reminder->remind_at->toDateTimeString())->toBe($now->copy()->addDays(3)->toDateTimeString())
        ->and($reminder->next_action)->toContain($quotation->number)
        ->and($reminder->next_action)->toContain('Prajakta Digital');
});

it('names the referring partner in the follow-up reminder when the client was referred', function () {
    Mail::fake();
    $partner = Partner::factory()->create(['name' => 'Prajakta Dahake']);
    $customer = Customer::factory()->create([
        'email' => 'client@x.test',
        'company_name' => 'Brand Whiz',
        'referring_partner_id' => $partner->id,
    ]);
    $quotation = quotationWithLine(['customer_id' => $customer->id]);
    $manager = User::factory()->role(UserRole::Manager)->create();
    $this->actingAs($manager)->post(route('quotations.approve', $quotation))->assertRedirect();

    $this->actingAs($this->admin)
        ->post(route('quotations.send', $quotation))
        ->assertSessionDoesntHaveErrors()
        ->assertRedirect();

    $reminder = FollowUpReminder::query()->where('customer_id', $customer->id)->first();

    expect($reminder)->not->toBeNull()
        ->and($reminder->next_action)->toContain('Prajakta Dahake')
        ->and($reminder->next_action)->toContain('Brand Whiz')
        ->and($reminder->next_action)->toContain($quotation->number);
});

it('bills a reseller-referred client\'s quotation to the reseller\'s own customer record, not the client directly', function () {
    $billTo = Customer::factory()->create(['company_name' => 'Brand Whiz', 'state_code' => '27']);
    $reseller = Partner::factory()->create(['name' => 'Brand-Whiz', 'billing_customer_id' => $billTo->id]);
    $client = Customer::factory()->create([
        'company_name' => 'ESS', 'state_code' => '19', 'referring_partner_id' => $reseller->id,
    ]);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->set('customer_id', $client->id)
        ->set('items', [[
            'description' => 'SEO Service for ESS for the month', 'sac_code' => '998361',
            'quantity' => '1', 'rate' => '1000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    $quotation = Quotation::first();

    expect($quotation->customer_id)->toBe($billTo->id)
        ->and($quotation->place_of_supply_state_code)->toBe('27') // billed party's state, not ESS's
        ->and($quotation->cgst_total)->toBeGreaterThan(0); // intra-state (27 vs 27), not IGST
});

it('bills an ordinary client (no reseller partner) to themselves as before', function () {
    $client = Customer::factory()->create(['company_name' => 'Regular Client', 'state_code' => '27']);

    Livewire::actingAs($this->admin)
        ->test(QuotationBuilder::class)
        ->set('customer_id', $client->id)
        ->set('items', [[
            'description' => 'SEO retainer', 'sac_code' => '998361',
            'quantity' => '1', 'rate' => '1000', 'gst_rate' => '18',
        ]])
        ->call('save')
        ->assertHasNoErrors();

    expect(Quotation::first()->customer_id)->toBe($client->id);
});

it('lets a manager reject or request changes on a quotation, and the creator can resubmit', function () {
    $quotation = quotationWithLine();
    $manager = User::factory()->role(UserRole::Manager)->create();

    $this->actingAs($manager)->post(route('quotations.request-changes', $quotation), ['approval_notes' => 'Fix the discount line'])->assertRedirect();
    expect($quotation->fresh()->approval_status)->toBe(QuotationApprovalStatus::ChangesRequested)
        ->and($quotation->fresh()->approval_notes)->toBe('Fix the discount line');

    Notification::fake();
    $this->actingAs($this->admin)->post(route('quotations.resubmit', $quotation))->assertRedirect();
    expect($quotation->fresh()->approval_status)->toBe(QuotationApprovalStatus::Pending)
        ->and($quotation->fresh()->approval_notes)->toBeNull();
    Notification::assertSentTo($manager, QuotationNeedsApproval::class);

    $this->actingAs($manager)->post(route('quotations.reject', $quotation), ['approval_notes' => 'Not viable'])->assertRedirect();
    expect($quotation->fresh()->approval_status)->toBe(QuotationApprovalStatus::Rejected);
});

it('forbids a non-manager from reviewing a quotation', function () {
    $sales = User::factory()->role(UserRole::Sales)->create();
    $quotation = quotationWithLine();

    $this->actingAs($sales)->post(route('quotations.approve', $quotation))->assertForbidden();
    $this->actingAs($sales)->post(route('quotations.reject', $quotation))->assertForbidden();
    $this->actingAs($sales)->post(route('quotations.request-changes', $quotation), ['approval_notes' => 'x'])->assertForbidden();
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

it('points the Back link on the create page at the deal it was started from, not the index', function () {
    $deal = Deal::factory()->create();

    $html = $this->actingAs($this->admin)
        ->get(route('quotations.create', ['customer_id' => $deal->customer_id, 'deal_id' => $deal->id]))
        ->assertOk()
        ->getContent();

    $backLink = 'href="'.route('deals.show', $deal).'" class="text-sm text-gray-500 hover:text-gray-700">Back</a>';
    expect($html)->toContain($backLink);
});

it('points the Back link at the index when the create page has no deal context', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('quotations.create'))
        ->assertOk()
        ->getContent();

    $backLink = 'href="'.route('quotations.index').'" class="text-sm text-gray-500 hover:text-gray-700">Back</a>';
    expect($html)->toContain($backLink);
});

it('points the Back link on the edit page at the quotation itself, not the index', function () {
    $quotation = quotationWithLine();

    $html = $this->actingAs($this->admin)
        ->get(route('quotations.edit', $quotation))
        ->assertOk()
        ->getContent();

    $backLink = 'href="'.route('quotations.show', $quotation).'" class="text-sm text-gray-500 hover:text-gray-700">Back</a>';
    expect($html)->toContain($backLink);
});

it('streams a PDF quotation', function () {
    $quotation = quotationWithLine();

    $response = $this->actingAs($this->admin)->get(route('quotations.pdf', $quotation));

    $response->assertOk();
    expect($response->getContent())->toStartWith('%PDF');
});

it('renders the quotation PDF template with non-GST wording and no tax breakup when GST-exempt', function () {
    $quotation = quotationWithLine(['is_gst_exempt' => true]);
    $quotation->load(['customer', 'items']);

    $html = view('quotations.pdf', ['quotation' => $quotation])->render();

    expect($html)->toContain('Non-GST Quotation')
        ->toContain('GST not charged')
        ->not->toContain('CGST');
});

it('shows a Download PDF link on the quotation show page', function () {
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)
        ->get(route('quotations.show', $quotation))
        ->assertOk()
        ->assertSee(route('quotations.pdf', $quotation), false);
});

it('shows a delete button on the quotation show page and deletes it', function () {
    $quotation = quotationWithLine();

    $this->actingAs($this->admin)->get(route('quotations.show', $quotation))->assertOk()->assertSee('Delete');

    $this->actingAs($this->admin)->delete(route('quotations.destroy', $quotation))->assertRedirect(route('quotations.index'));
    expect(Quotation::find($quotation->id))->toBeNull();
});
