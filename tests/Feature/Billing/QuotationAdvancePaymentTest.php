<?php

use App\Actions\GenerateMilestoneInvoice;
use App\Enums\QuotationApprovalStatus;
use App\Enums\QuotationStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.razorpay.key_id' => 'rzp_test_key',
        'services.razorpay.key_secret' => 'test-key-secret',
    ]);
});

function quotationWithMilestone(array $milestoneOverrides = [], array $quotationOverrides = []): Quotation
{
    $quotation = Quotation::factory()->create(array_merge([
        'place_of_supply_state_code' => '27',
        'status' => QuotationStatus::Sent,
        'approval_status' => QuotationApprovalStatus::Approved,
        'customer_id' => Customer::factory()->create(['state_code' => '27']),
    ], $quotationOverrides));

    $quotation->items()->create([
        'description' => 'Website build',
        'sac_code' => '998361',
        'quantity' => 1,
        'rate' => 100000,
        'gst_rate' => 18,
        'amount' => 100000,
    ]);
    $quotation->refresh()->recalculateTotals();

    $quotation->milestones()->create(array_merge([
        'title' => 'Advance',
        'percentage' => 50,
        'amount' => 59000,
        'sort_order' => 0,
    ], $milestoneOverrides));

    $quotation->refresh()->publicViewUrl(); // lazily generates+persists public_token

    return $quotation->refresh();
}

// ──────────────────────────────────────────────────────────────────────────────
// Quotation::nextPayableMilestone()
// ──────────────────────────────────────────────────────────────────────────────

it('returns the earliest unbilled milestone for a Sent quotation', function () {
    $quotation = quotationWithMilestone();
    $quotation->milestones()->create(['title' => 'Balance', 'percentage' => 50, 'amount' => 59000, 'sort_order' => 1]);

    $next = $quotation->nextPayableMilestone();

    expect($next->title)->toBe('Advance');
});

it('skips an already-billed milestone and returns the next one', function () {
    $quotation = quotationWithMilestone();
    $balance = $quotation->milestones()->create(['title' => 'Balance', 'percentage' => 50, 'amount' => 59000, 'sort_order' => 1]);
    $quotation->milestones()->first()->update(['invoice_id' => Invoice::factory()->create()->id]);

    expect($quotation->nextPayableMilestone()->id)->toBe($balance->id);
});

it('returns null when every milestone is already billed', function () {
    $quotation = quotationWithMilestone();
    $quotation->milestones()->first()->update(['invoice_id' => Invoice::factory()->create()->id]);

    expect($quotation->nextPayableMilestone())->toBeNull();
});

it('returns null for a Draft quotation even with an unbilled milestone', function () {
    $quotation = quotationWithMilestone(quotationOverrides: ['status' => QuotationStatus::Draft, 'approval_status' => QuotationApprovalStatus::Pending]);

    expect($quotation->nextPayableMilestone())->toBeNull();
});

it('returns null for a quotation with no milestones at all', function () {
    $quotation = Quotation::factory()->create(['status' => QuotationStatus::Sent]);

    expect($quotation->nextPayableMilestone())->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// GenerateMilestoneInvoice::handleOrExisting()
// ──────────────────────────────────────────────────────────────────────────────

it('handleOrExisting() creates an invoice for an unbilled milestone', function () {
    $quotation = quotationWithMilestone();
    $milestone = $quotation->milestones()->first();

    $invoice = app(GenerateMilestoneInvoice::class)->handleOrExisting($milestone);

    // GenerateMilestoneInvoice prorates the ORIGINAL item amount (100000)
    // by the milestone percentage (50%) — 50000 — then applies GST on top,
    // independently of whatever milestone.amount happens to be set to.
    expect($invoice->id)->not->toBeNull()
        ->and($milestone->fresh()->invoice_id)->toBe($invoice->id)
        ->and($invoice->total)->toBe(50000 + (int) round(50000 * 0.18));
});

it('handleOrExisting() returns the existing invoice for an already-billed milestone without creating a second one', function () {
    $quotation = quotationWithMilestone();
    $milestone = $quotation->milestones()->first();
    $first = app(GenerateMilestoneInvoice::class)->handleOrExisting($milestone);

    $second = app(GenerateMilestoneInvoice::class)->handleOrExisting($milestone->fresh());

    expect($second->id)->toBe($first->id)
        ->and(Invoice::where('quotation_id', $quotation->id)->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// QuotationAdvancePaymentController::order()
// ──────────────────────────────────────────────────────────────────────────────

it('creates a Razorpay order for the next payable milestone, generating its invoice on the fly', function () {
    Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_va1', 'amount' => 69620, 'currency' => 'INR'])]);
    $quotation = quotationWithMilestone();
    $milestone = $quotation->milestones()->first();

    $this->postJson(route('quotations.public-pay.order', $quotation->public_token))
        ->assertOk()
        ->assertJson(['order_id' => 'order_va1', 'key_id' => 'rzp_test_key', 'milestone_title' => 'Advance']);

    $invoiceId = $milestone->fresh()->invoice_id;
    expect($invoiceId)->not->toBeNull();

    Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
        && $request['notes']['invoice_id'] === (string) $invoiceId);
});

it('404s order() for an unknown token', function () {
    $this->postJson(route('quotations.public-pay.order', 'not-a-real-token'))->assertNotFound();
});

it('422s order() when the quotation has no milestone currently due', function () {
    $quotation = Quotation::factory()->create(['status' => QuotationStatus::Sent]);
    $quotation->publicViewUrl();

    $this->postJson(route('quotations.public-pay.order', $quotation->public_token))->assertStatus(422);
});

it('returns 503 from order() when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);
    $quotation = quotationWithMilestone();

    $this->postJson(route('quotations.public-pay.order', $quotation->public_token))->assertStatus(503);
});

// ──────────────────────────────────────────────────────────────────────────────
// QuotationAdvancePaymentController::verify()
// ──────────────────────────────────────────────────────────────────────────────

it('verifies a correctly signed advance payment and records it against the milestone invoice', function () {
    $quotation = quotationWithMilestone();
    $milestone = $quotation->milestones()->first();
    $invoice = app(GenerateMilestoneInvoice::class)->handleOrExisting($milestone);

    Http::fake(['api.razorpay.com/v1/orders/order_va1' => Http::response([
        'id' => 'order_va1', 'amount' => $invoice->total, 'notes' => ['invoice_id' => (string) $invoice->id],
    ])]);
    $signature = hash_hmac('sha256', 'order_va1|pay_va1', 'test-key-secret');

    $this->postJson(route('quotations.public-pay.verify', $quotation->public_token), [
        'razorpay_order_id' => 'order_va1',
        'razorpay_payment_id' => 'pay_va1',
        'razorpay_signature' => $signature,
    ])->assertOk()->assertJson(['status' => 'ok']);

    $payment = Payment::where('gateway_payment_id', 'pay_va1')->first();
    expect($payment)->not->toBeNull()
        ->and($payment->invoice_id)->toBe($invoice->id)
        ->and($payment->amount)->toBe($invoice->total);
    expect($invoice->fresh()->balance())->toBe(0);
});

it('rejects verify with a bad signature and records nothing', function () {
    $quotation = quotationWithMilestone();

    $this->postJson(route('quotations.public-pay.verify', $quotation->public_token), [
        'razorpay_order_id' => 'order_va1',
        'razorpay_payment_id' => 'pay_va1',
        'razorpay_signature' => 'not-the-real-signature',
    ])->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('rejects verify when the fetched order\'s invoice does not belong to this quotation', function () {
    $quotation = quotationWithMilestone();
    $otherQuotation = quotationWithMilestone();
    $otherInvoice = app(GenerateMilestoneInvoice::class)->handleOrExisting($otherQuotation->milestones()->first());

    Http::fake(['api.razorpay.com/v1/orders/order_va1' => Http::response([
        'id' => 'order_va1', 'amount' => $otherInvoice->total, 'notes' => ['invoice_id' => (string) $otherInvoice->id],
    ])]);
    $signature = hash_hmac('sha256', 'order_va1|pay_va1', 'test-key-secret');

    $this->postJson(route('quotations.public-pay.verify', $quotation->public_token), [
        'razorpay_order_id' => 'order_va1',
        'razorpay_payment_id' => 'pay_va1',
        'razorpay_signature' => $signature,
    ])->assertStatus(422);

    expect(Payment::count())->toBe(0);
});

it('404s verify() for an unknown token', function () {
    $this->postJson(route('quotations.public-pay.verify', 'not-a-real-token'), [
        'razorpay_order_id' => 'x', 'razorpay_payment_id' => 'y', 'razorpay_signature' => 'z',
    ])->assertNotFound();
});

// ──────────────────────────────────────────────────────────────────────────────
// Public view page — Pay Advance button
// ──────────────────────────────────────────────────────────────────────────────

it('shows the Pay Advance button on the public view page when a milestone is due and Razorpay is configured', function () {
    $quotation = quotationWithMilestone();

    $this->get($quotation->publicViewUrl())
        ->assertOk()
        ->assertSee('Pay')
        ->assertSee('Advance');
});

it('hides the Pay Advance button when Razorpay is not configured', function () {
    config(['services.razorpay.key_id' => null, 'services.razorpay.key_secret' => null]);
    $quotation = quotationWithMilestone();

    $this->get($quotation->publicViewUrl())
        ->assertOk()
        ->assertDontSee('Pay 590');
});

it('hides the Pay Advance button when there is no milestone currently due', function () {
    $quotation = Quotation::factory()->create(['status' => QuotationStatus::Sent]);
    $quotation->items()->create([
        'description' => 'Retainer', 'sac_code' => '998361', 'quantity' => 1,
        'rate' => 100000, 'gst_rate' => 18, 'amount' => 100000,
    ]);
    $quotation->refresh()->recalculateTotals();

    $this->get($quotation->publicViewUrl())
        ->assertOk()
        ->assertDontSee('Pay advance');
});
