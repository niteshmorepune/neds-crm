<?php

use App\Enums\CallDirection;
use App\Enums\CallOutcome;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.whatsapp_webhook.token' => 'wa-webhook-secret']);
});

function postCallLog(array $overrides = []): TestResponse
{
    return test()->postJson('/api/webhooks/wadesk/call-log', array_merge([
        'phone' => '919876543210',
        'agent_email' => 'agent@example.test',
        'wadesk_call_id' => 'wacid_'.uniqid(),
        'started_at' => now()->subMinute()->toIso8601String(),
        'duration_seconds' => 29,
    ], $overrides), ['Authorization' => 'Bearer wa-webhook-secret']);
}

it('creates a CallLog for the matching Lead when the agent email matches a real CRM user', function () {
    $user = User::factory()->create(['email' => 'agent@example.test']);
    $lead = Lead::factory()->create(['phone' => '919876543210']);

    postCallLog()
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $callLog = CallLog::first();
    expect($callLog)->not->toBeNull()
        ->and($callLog->user_id)->toBe($user->id)
        ->and($callLog->callable_type)->toBe(Lead::class)
        ->and($callLog->callable_id)->toBe($lead->id)
        ->and($callLog->direction)->toBe(CallDirection::Incoming)
        ->and($callLog->outcome)->toBe(CallOutcome::Connected)
        ->and($callLog->duration_minutes)->toBe(0); // round(29/60) = 0
});

it('prefers a matching Customer over a Lead with the same phone', function () {
    User::factory()->create(['email' => 'agent@example.test']);
    $customer = Customer::factory()->create(['phone' => '9876543210']);
    Lead::factory()->create(['phone' => '919876543210']);

    postCallLog(['phone' => '+91 98765 43210'])->assertOk();

    $callLog = CallLog::first();
    expect($callLog->callable_type)->toBe(Customer::class)
        ->and($callLog->callable_id)->toBe($customer->id);
});

it('creates a CallLog with no callable when the phone matches neither a Customer nor an open Lead', function () {
    User::factory()->create(['email' => 'agent@example.test']);

    postCallLog(['phone' => '910000000000'])
        ->assertOk()
        ->assertJson(['status' => 'created']);

    $callLog = CallLog::first();
    expect($callLog->callable_type)->toBeNull()
        ->and($callLog->callable_id)->toBeNull();
});

it('rounds duration_seconds to the nearest minute', function () {
    User::factory()->create(['email' => 'agent@example.test']);

    postCallLog(['duration_seconds' => 95])->assertOk(); // round(95/60) = round(1.583) = 2

    expect(CallLog::first()->duration_minutes)->toBe(2);
});

it('does not create a CallLog when no CRM user matches the agent email', function () {
    postCallLog()
        ->assertOk()
        ->assertJson(['status' => 'no_matching_user']);

    expect(CallLog::count())->toBe(0);
});

it('dedupes on wadesk_call_id -- a retried delivery never double-logs the same call', function () {
    User::factory()->create(['email' => 'agent@example.test']);

    postCallLog(['wadesk_call_id' => 'wacid_dupe_test'])->assertOk()->assertJson(['status' => 'created']);
    postCallLog(['wadesk_call_id' => 'wacid_dupe_test'])->assertOk()->assertJson(['status' => 'duplicate']);

    expect(CallLog::count())->toBe(1);
});

it('rejects requests without the correct bearer token', function () {
    test()->postJson('/api/webhooks/wadesk/call-log', [
        'phone' => '919876543210',
        'agent_email' => 'agent@example.test',
        'wadesk_call_id' => 'wacid_no_auth',
        'started_at' => now()->toIso8601String(),
        'duration_seconds' => 30,
    ])->assertUnauthorized();

    expect(CallLog::count())->toBe(0);
});

it('validates required fields', function () {
    test()->postJson('/api/webhooks/wadesk/call-log', [], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'agent_email', 'wadesk_call_id', 'started_at', 'duration_seconds']);
});
