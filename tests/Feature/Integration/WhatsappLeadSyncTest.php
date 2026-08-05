<?php

use App\Enums\UserRole;
use App\Jobs\SyncLeadToWadeskJob;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config([
        'services.wadesk.base_url' => 'https://wadesk.test',
        'services.wadesk.service_key' => 'wadesk-secret',
        'services.wadesk.marketing_number' => '919112095202',
    ]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Dispatch behaviour (LeadObserver wiring)
// ──────────────────────────────────────────────────────────────────────────────

it('dispatches SyncLeadToWadeskJob exactly once when a new lead is auto-assigned', function () {
    Queue::fake();
    User::factory()->role(UserRole::Sales)->create();

    $lead = Lead::factory()->create();

    Queue::assertPushed(SyncLeadToWadeskJob::class, 1);
    Queue::assertPushed(SyncLeadToWadeskJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches SyncLeadToWadeskJob exactly once when a new lead is left unowned', function () {
    Queue::fake();

    $lead = Lead::factory()->create();

    Queue::assertPushed(SyncLeadToWadeskJob::class, 1);
    Queue::assertPushed(SyncLeadToWadeskJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('dispatches SyncLeadToWadeskJob again when a lead is manually reassigned', function () {
    $original = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($original->id)->create();
    $newOwner = User::factory()->role(UserRole::Sales)->create();

    Queue::fake();
    $lead->update(['owner_id' => $newOwner->id]);

    Queue::assertPushed(SyncLeadToWadeskJob::class, fn ($job) => $job->leadId === $lead->id);
});

it('does not dispatch SyncLeadToWadeskJob on an update that leaves owner_id unchanged', function () {
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create();

    Queue::fake();
    $lead->update(['company' => 'New Company Name']);

    Queue::assertNotPushed(SyncLeadToWadeskJob::class);
});

// ──────────────────────────────────────────────────────────────────────────────
// Job execution
// ──────────────────────────────────────────────────────────────────────────────

it('POSTs the normalized phone, marketing businessNumber, name, and owner email to wadesk.in', function () {
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'conv_new'], 200)]);

    $owner = User::factory()->create(['email' => 'kiran@niranjanenterprises.co.in']);
    $lead = Lead::factory()->ownedBy($owner->id)->create(['name' => 'Ravi Kumar', 'phone' => '+91 90280 99919']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://wadesk.test/api/leads/sync'
            && $request->header('X-Service-Key')[0] === 'wadesk-secret'
            && $request['phone'] === '919028099919'
            && $request['name'] === 'Ravi Kumar'
            && $request['businessNumber'] === '919112095202'
            && $request['agentEmail'] === 'kiran@niranjanenterprises.co.in';
    });
});

it('omits agentEmail when the lead has no owner yet', function () {
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'conv_new'], 200)]);

    $lead = Lead::factory()->create(['phone' => '919028099919']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertSent(fn ($request) => $request['agentEmail'] === null);
});

it('stores the returned conversationId on the lead when it has none yet', function () {
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'conv_new'], 200)]);

    $lead = Lead::factory()->create(['phone' => '919028099919', 'whatsapp_conversation_id' => null]);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    expect($lead->fresh()->whatsapp_conversation_id)->toBe('conv_new');
});

it('does not overwrite an existing whatsapp_conversation_id', function () {
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['conversationId' => 'conv_different'], 200)]);

    $lead = Lead::factory()->create(['phone' => '919028099919', 'whatsapp_conversation_id' => 'conv_original']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    expect($lead->fresh()->whatsapp_conversation_id)->toBe('conv_original');
});

it('skips the HTTP call when the marketing number is not configured', function () {
    Http::fake();
    config(['services.wadesk.marketing_number' => null]);

    $lead = Lead::factory()->create(['phone' => '919028099919']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call silently when wadesk config is not set', function () {
    Http::fake();
    config(['services.wadesk.service_key' => null]);

    $lead = Lead::factory()->create(['phone' => '919028099919']);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the lead has no phone', function () {
    Http::fake();

    $lead = Lead::factory()->create(['phone' => null]);

    (new SyncLeadToWadeskJob($lead->id))->handle();

    Http::assertNothingSent();
});

it('skips the HTTP call when the lead no longer exists', function () {
    Http::fake();

    (new SyncLeadToWadeskJob(999999))->handle();

    Http::assertNothingSent();
});

it('logs a warning but does not throw when wadesk.in returns an error', function () {
    Http::fake(['https://wadesk.test/api/leads/sync' => Http::response(['error' => 'Not found'], 404)]);

    $lead = Lead::factory()->create(['phone' => '919028099919']);

    expect(fn () => (new SyncLeadToWadeskJob($lead->id))->handle())->not->toThrow(Throwable::class);
});

it('logs a warning but does not throw when wadesk.in is unreachable', function () {
    Http::fake(['*' => fn () => throw new ConnectionException('Connection refused')]);

    $lead = Lead::factory()->create(['phone' => '919028099919']);

    expect(fn () => (new SyncLeadToWadeskJob($lead->id))->handle())->not->toThrow(Throwable::class);
});
