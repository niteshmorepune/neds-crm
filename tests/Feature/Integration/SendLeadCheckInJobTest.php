<?php

use App\Jobs\SendLeadCheckInJob;
use App\Models\Lead;
use App\Models\Service;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
        'services.wadesk.lead_checkin_template_name' => 'lead_checkin',
    ]);

    // Lead::factory()->create() below still fires LeadObserver::created(),
    // which dispatches SendTelegramLeadAlertJob/ScoreLead/etc -- with
    // QUEUE_CONNECTION=sync those would otherwise really run and make their
    // own HTTP calls, polluting the blanket Http::fake() this file uses.
    Queue::fake();
});

it('sends the check-in template with the lead\'s name and service, and records it', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1', 'messageId' => 'wamsg_2'], 201)]);
    $seo = Service::factory()->create(['name' => 'SEO', 'is_active' => true]);
    $lead = Lead::factory()->create(['name' => 'Priya Shah', 'phone' => '+91 98765 43210', 'service_id' => $seo->id]);

    (new SendLeadCheckInJob($lead->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/send-template'
            && $request['phone'] === '919876543210'
            && $request['templateName'] === 'lead_checkin'
            && $request['variables'] === ['Priya Shah', 'SEO', 'Priya Shah', 'SEO'];
    });

    expect($lead->fresh()->last_checkin_sent_at)->not->toBeNull();
    expect($lead->fresh()->checkin_wadesk_id)->toBe('wamsg_2');
    expect($lead->notes()->latest()->first()->body)->toContain('Re-engagement check-in sent via WhatsApp');
});

it('is re-sendable -- no idempotency guard of its own', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['conversationId' => 'c1'], 201)]);
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210', 'last_checkin_sent_at' => now()->subHour()]);

    (new SendLeadCheckInJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request->url() === 'https://wadesk.test/api/send-template');
});

it('still updates the timestamp, without a note, when wadesk.in skips an opted-out contact', function () {
    Http::fake(['https://wadesk.test/api/send-template' => Http::response(['skipped' => true, 'reason' => 'opted_out'], 200)]);
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);

    (new SendLeadCheckInJob($lead->id))->handle();

    expect($lead->fresh()->last_checkin_sent_at)->not->toBeNull()
        ->and($lead->fresh()->checkin_wadesk_id)->toBeNull()
        ->and($lead->notes()->count())->toBe(0);
});

it('does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);

    expect(fn () => (new SendLeadCheckInJob($lead->id))->handle())->not->toThrow(Throwable::class);
});

it('no-ops when the template is not configured', function () {
    config(['services.wadesk.lead_checkin_template_name' => null]);
    Http::fake();
    $lead = Lead::factory()->create(['phone' => '+91 98765 43210']);

    (new SendLeadCheckInJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead has no phone', function () {
    Http::fake();
    $lead = Lead::factory()->create(['phone' => null]);

    (new SendLeadCheckInJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('no-ops when the lead no longer exists', function () {
    Http::fake();

    (new SendLeadCheckInJob(999999))->handle();

    Http::assertNothingSent();
});
