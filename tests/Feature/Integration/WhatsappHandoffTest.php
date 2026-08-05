<?php

use App\Enums\DealStage;
use App\Jobs\SendWhatsappHandoffMessageJob;
use App\Models\Customer;
use App\Models\Deal;
use Database\Seeders\MenuItemsSeeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.support_number' => '918007733737',
        'services.wadesk.handoff_template_name' => 'welcome_to_support',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SendWhatsappHandoffMessageJob when a deal moves to Won', function () {
    Queue::fake();
    $deal = Deal::factory()->stage(DealStage::Proposal)->create();

    $deal->update(['stage' => DealStage::Won]);

    Queue::assertPushed(
        SendWhatsappHandoffMessageJob::class,
        fn ($job) => $job->customerId === $deal->customer_id,
    );
});

it('does not dispatch the handoff job when a deal moves to a non-Won stage', function () {
    Queue::fake();
    $deal = Deal::factory()->stage(DealStage::New)->create();

    $deal->update(['stage' => DealStage::Negotiation]);

    Queue::assertNotPushed(SendWhatsappHandoffMessageJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the normalized phone, support businessNumber, template name, and customer name variable to wadesk.in', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);

    $customer = Customer::factory()->create(['company_name' => 'Acme Retail', 'phone' => '+91 90280 99919']);

    (new SendWhatsappHandoffMessageJob($customer->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['phone'] === '919028099919'
            && $request['businessNumber'] === '918007733737'
            && $request['templateName'] === 'welcome_to_support'
            && $request['variables'] === ['Acme Retail']
            && $request['resolveOtherLines'] === true;
    });
});

it('skips the HTTP call when the handoff template name is not configured', function () {
    Http::fake();
    config(['services.wadesk.handoff_template_name' => null]);

    $customer = Customer::factory()->create(['phone' => '919028099919']);

    (new SendWhatsappHandoffMessageJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the support number is not configured', function () {
    Http::fake();
    config(['services.wadesk.support_number' => null]);

    $customer = Customer::factory()->create(['phone' => '919028099919']);

    (new SendWhatsappHandoffMessageJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call silently when wadesk config is not set', function () {
    Http::fake();
    config(['services.wadesk.service_key' => null]);

    $customer = Customer::factory()->create(['phone' => '919028099919']);

    (new SendWhatsappHandoffMessageJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the customer has no phone', function () {
    Http::fake();

    $customer = Customer::factory()->create(['phone' => null]);

    (new SendWhatsappHandoffMessageJob($customer->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the customer no longer exists', function () {
    Http::fake();

    (new SendWhatsappHandoffMessageJob(999999))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in returns an error', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['error' => 'Not found'], 404)]);

    $customer = Customer::factory()->create(['phone' => '919028099919']);

    expect(fn () => (new SendWhatsappHandoffMessageJob($customer->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $customer = Customer::factory()->create(['phone' => '919028099919']);

    expect(fn () => (new SendWhatsappHandoffMessageJob($customer->id))->handle())->not->toThrow(Throwable::class);
});
