<?php

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\User;
use App\Notifications\SmdostBriefApproved;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config(['services.smdost.service_key' => 'test-smdost-secret']);

    $this->validPayload = [
        'smdost_client_id' => 'smd-client-abc',
        'brief_id'         => 'brief-xyz-123',
        'brief_title'      => 'June Social Campaign',
        'scheduled_month'  => '2026-06',
        'post_count'       => 8,
    ];
});

function smdostPost(array $payload, string $token = 'test-smdost-secret'): \Illuminate\Testing\TestResponse
{
    return test()->withToken($token)
        ->postJson('/api/webhooks/smdost/brief-approved', $payload);
}

// ──────────────────────────────────────────────────────────────────────────────
// Authentication
// ──────────────────────────────────────────────────────────────────────────────

it('rejects requests with no token', function () {
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    test()->postJson('/api/webhooks/smdost/brief-approved', $this->validPayload)
        ->assertStatus(401);

    expect(Invoice::where('customer_id', $customer->id)->count())->toBe(0);
});

it('rejects requests with a wrong token', function () {
    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload, 'wrong-key')->assertStatus(401);
});

it('accepts requests with the correct token', function () {
    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload)->assertOk();
});

// ──────────────────────────────────────────────────────────────────────────────
// Customer lookup
// ──────────────────────────────────────────────────────────────────────────────

it('returns no_customer_match when smdost_client_id is unknown', function () {
    smdostPost($this->validPayload)
        ->assertOk()
        ->assertJson(['status' => 'no_customer_match']);

    expect(Invoice::count())->toBe(0);
});

it('matches the customer by smdost_client_id', function () {
    Notification::fake();
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload)->assertJson(['status' => 'created']);

    expect(Invoice::where('customer_id', $customer->id)->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Draft invoice creation
// ──────────────────────────────────────────────────────────────────────────────

it('creates a draft invoice with no invoice number', function () {
    Notification::fake();
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload);

    $invoice = Invoice::where('customer_id', $customer->id)->first();
    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->invoice_number)->toBeNull();
});

it('creates the invoice with correct dates', function () {
    Notification::fake();
    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    $before = now()->toDateString();
    smdostPost($this->validPayload);
    $after = now()->addDays(30)->toDateString();

    $invoice = Invoice::latest()->first();
    expect($invoice->issue_date->toDateString())->toBe($before)
        ->and($invoice->due_date->toDateString())->toBe($after);
});

it('creates one placeholder line item with the brief title and month', function () {
    Notification::fake();
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload);

    $invoice  = Invoice::where('customer_id', $customer->id)->first();
    $item     = InvoiceItem::where('invoice_id', $invoice->id)->first();

    expect($item)->not->toBeNull()
        ->and($item->description)->toContain('June Social Campaign')
        ->and($item->description)->toContain('Jun 2026')
        ->and($item->quantity)->toBe('1.00')
        ->and($item->rate)->toBe(0)
        ->and($item->gst_rate)->toBe('18.00')
        ->and($item->sac_code)->toBe('998361');
});

it('sets state code from customer and defaults to 27 when absent', function () {
    Notification::fake();
    Customer::factory()->create([
        'smdost_client_id' => 'smd-client-abc',
        'state_code'       => null,
    ]);

    smdostPost($this->validPayload);

    $invoice = Invoice::latest()->first();
    expect($invoice->place_of_supply_state_code)->toBe('27')
        ->and($invoice->is_intra_state)->toBeTrue();
});

it('sets is_intra_state false for non-Maharashtra customers', function () {
    Notification::fake();
    Customer::factory()->create([
        'smdost_client_id' => 'smd-client-abc',
        'state_code'       => '29', // Karnataka
    ]);

    smdostPost($this->validPayload);

    $invoice = Invoice::latest()->first();
    expect($invoice->is_intra_state)->toBeFalse();
});

it('returns the invoice id in the response', function () {
    Notification::fake();
    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    $response = smdostPost($this->validPayload)->assertJson(['status' => 'created']);
    $invoiceId = $response->json('invoice_id');

    expect(Invoice::find($invoiceId))->not->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────────
// Notifications
// ──────────────────────────────────────────────────────────────────────────────

it('notifies all accounts and admin users', function () {
    Notification::fake();

    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);
    $accounts = User::factory()->create(['role' => UserRole::Accounts->value]);
    $admin    = User::factory()->create(['role' => UserRole::Admin->value]);
    $sales    = User::factory()->create(['role' => UserRole::Sales->value]);

    smdostPost($this->validPayload);

    Notification::assertSentTo($accounts, SmdostBriefApproved::class);
    Notification::assertSentTo($admin, SmdostBriefApproved::class);
    Notification::assertNotSentTo($sales, SmdostBriefApproved::class);
});

it('notification payload contains the invoice url and brief title', function () {
    Notification::fake();
    Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);
    $accounts = User::factory()->create(['role' => UserRole::Accounts->value]);

    smdostPost($this->validPayload);

    Notification::assertSentTo($accounts, SmdostBriefApproved::class, function ($notification) use ($accounts) {
        $data = $notification->toArray($accounts);
        return str_contains($data['url'], '/invoices/')
            && $data['brief_title'] === 'June Social Campaign';
    });
});

// ──────────────────────────────────────────────────────────────────────────────
// Activity log
// ──────────────────────────────────────────────────────────────────────────────

it('writes an activity log entry on the customer', function () {
    Notification::fake();
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-client-abc']);

    smdostPost($this->validPayload);

    $activity = $customer->activities()->where('event', 'updated')->latest()->first();
    expect($activity)->not->toBeNull()
        ->and($activity->changes)->toHaveKey('smdost_brief_approved')
        ->and($activity->changes)->toHaveKey('draft_invoice_created');
});

// ──────────────────────────────────────────────────────────────────────────────
// Validation
// ──────────────────────────────────────────────────────────────────────────────

it('rejects missing required fields', function (string $field) {
    $payload = $this->validPayload;
    unset($payload[$field]);

    smdostPost($payload)->assertStatus(422);
})->with(['smdost_client_id', 'brief_id', 'brief_title', 'scheduled_month', 'post_count']);
