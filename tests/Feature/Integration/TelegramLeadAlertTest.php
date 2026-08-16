<?php

use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Jobs\SendTelegramLeadAlertJob;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.telegram.bot_token' => 'test-bot-token',
        'services.telegram.chat_id' => '-100123456789',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SendTelegramLeadAlertJob whenever a lead is created', function () {
    Queue::fake();

    $lead = Lead::factory()->create();

    Queue::assertPushed(SendTelegramLeadAlertJob::class, fn ($job) => $job->leadId === $lead->id);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the lead detail, phone, source, assignee, and CRM link to the Telegram Bot API', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $owner = User::factory()->role(UserRole::Sales)->create(['name' => 'Kiran Katte']);
    $lead = Lead::factory()->ownedBy($owner->id)->create([
        'name' => 'Priya Shah',
        'company' => 'Shah Traders',
        'phone' => '9876543210',
        'source' => LeadSource::MetaAds,
    ]);

    (new SendTelegramLeadAlertJob($lead->id))->handle();

    Http::assertSent(function ($request) use ($lead) {
        return $request->url() === 'https://api.telegram.org/bottest-bot-token/sendMessage'
            && $request['chat_id'] === '-100123456789'
            && str_contains($request['text'], 'Priya Shah (Shah Traders)')
            && str_contains($request['text'], 'Phone: 9876543210')
            && str_contains($request['text'], 'Kiran Katte')
            && str_contains($request['text'], (string) $lead->id);
    });
});

it('reports "not provided" when the lead has no phone number', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $lead = Lead::factory()->create(['phone' => null]);

    (new SendTelegramLeadAlertJob($lead->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request['text'], 'Phone: not provided'));
});

it('reports "unassigned" when the lead has no owner yet', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true], 200)]);

    $lead = Lead::factory()->create(['owner_id' => null]);

    (new SendTelegramLeadAlertJob($lead->id))->handle();

    Http::assertSent(fn ($request) => str_contains($request['text'], 'unassigned'));
});

it('skips the HTTP call when the bot token is not configured', function () {
    Http::fake();
    config(['services.telegram.bot_token' => null]);

    $lead = Lead::factory()->create();

    (new SendTelegramLeadAlertJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the chat id is not configured', function () {
    Http::fake();
    config(['services.telegram.chat_id' => null]);

    $lead = Lead::factory()->create();

    (new SendTelegramLeadAlertJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the lead no longer exists', function () {
    Http::fake();

    (new SendTelegramLeadAlertJob(999999))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when Telegram returns an error', function () {
    Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => false], 400)]);

    $lead = Lead::factory()->create();

    expect(fn () => (new SendTelegramLeadAlertJob($lead->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when Telegram is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = Lead::factory()->create();

    expect(fn () => (new SendTelegramLeadAlertJob($lead->id))->handle())->not->toThrow(Throwable::class);
});
