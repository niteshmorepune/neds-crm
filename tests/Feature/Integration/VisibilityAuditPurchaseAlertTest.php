<?php

use App\Enums\VisibilityAuditTier;
use App\Jobs\SendVisibilityAuditPurchaseAlertJob;
use App\Models\Lead;
use App\Models\VisibilityAuditPurchase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.chat_id' => '-100123456789',
    ]);
});

function purchaseForAlert(array $overrides = []): VisibilityAuditPurchase
{
    return VisibilityAuditPurchase::create(array_merge([
        'tier' => VisibilityAuditTier::Gbp,
        'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_alert_'.uniqid(),
        'payer_name' => 'Rakesh Kadam',
        'payer_phone' => '+91 89761 42982',
    ], $overrides));
}

it('POSTs the amount, tier, payer detail, and lead link to the Telegram Bot API when a lead is matched', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $lead = Lead::factory()->create();
    $purchase = purchaseForAlert(['lead_id' => $lead->id]);

    (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && $request['chat_id'] === '-100123456789'
            && str_contains($request['text'], '₹120')
            && str_contains($request['text'], 'GBP Audit')
            && str_contains($request['text'], 'Rakesh Kadam')
            && str_contains($request['text'], '+91 89761 42982')
            && str_contains($request['text'], (string) $lead->id);
    });
});

it('still alerts, with a note that no Lead was matched, when the purchase has no lead_id', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $purchase = purchaseForAlert(['lead_id' => null]);

    (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request['text'], 'no phone to match a Lead'));
});

it('falls back to a generic tier label when the amount matched no known tier', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $purchase = purchaseForAlert(['tier' => null, 'amount_paise' => 99999]);

    (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request['text'], 'Visibility Audit paid'));
});

it('skips the HTTP call when the bot token is not configured', function () {
    Http::fake();
    config(['services.telegram.bot_token' => null]);

    $purchase = purchaseForAlert();

    (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the chat id is not configured', function () {
    Http::fake();
    config(['services.telegram.chat_id' => null]);

    $purchase = purchaseForAlert();

    (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the purchase no longer exists', function () {
    Http::fake();

    (new SendVisibilityAuditPurchaseAlertJob(999999))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when Telegram returns an error', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false], 400)]);

    $purchase = purchaseForAlert();

    expect(fn () => (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when Telegram is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $purchase = purchaseForAlert();

    expect(fn () => (new SendVisibilityAuditPurchaseAlertJob($purchase->id))->handle())->not->toThrow(Throwable::class);
});
