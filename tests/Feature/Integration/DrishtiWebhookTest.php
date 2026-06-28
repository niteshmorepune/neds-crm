<?php

use App\Models\Activity;
use App\Models\Customer;
use Database\Seeders\MenuItemsSeeder;

beforeEach(function () {
    $this->seed(MenuItemsSeeder::class);

    config(['services.drishti.webhook_secret' => 'test-drishti-webhook-secret']);
});

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Build and send a signed Drishti webhook request.
 * Computes the HMAC on the exact JSON bytes that will arrive at the middleware.
 */
function drishtiEvent(
    array $data,
    string $event,
    string $secret = 'test-drishti-webhook-secret',
    ?int $timestamp = null,
): \Illuminate\Testing\TestResponse {
    $ts      = $timestamp ?? now()->timestamp;
    $payload = ['event' => $event, 'timestamp' => $ts, 'data' => $data];
    $body    = json_encode($payload);
    $sig     = 'sha256=' . hash_hmac('sha256', "{$ts}.{$body}", $secret);

    return test()->call(
        'POST',
        '/api/webhooks/drishti/event',
        [], [], [],
        [
            'CONTENT_TYPE'             => 'application/json',
            'HTTP_X-Agency-Event'      => $event,
            'HTTP_X-Agency-Signature'  => $sig,
            'HTTP_X-Agency-Timestamp'  => (string) $ts,
        ],
        $body,
    );
}

// ─── Authentication ───────────────────────────────────────────────────────────

it('rejects requests with no signature headers', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    test()->postJson('/api/webhooks/drishti/event', [])->assertStatus(401);
});

it('rejects requests with a wrong signature', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(['clientId' => 'drishti-abc', 'postId' => 'p1'], 'post.approved', 'wrong-secret')
        ->assertStatus(401);
});

it('rejects replayed requests older than 5 minutes', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    $staleTimestamp = now()->subMinutes(6)->timestamp;

    drishtiEvent(
        ['clientId' => 'drishti-abc', 'postId' => 'p1'],
        'post.approved',
        'test-drishti-webhook-secret',
        $staleTimestamp,
    )->assertStatus(401);
});

it('accepts requests with correct signature and fresh timestamp', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(['clientId' => 'drishti-abc', 'postId' => 'p1'], 'post.approved')
        ->assertOk();
});

// ─── Client lookup ────────────────────────────────────────────────────────────

it('returns no_client_id when payload has no clientId', function () {
    drishtiEvent(['postId' => 'p1'], 'post.approved')
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reason' => 'no_client_id']);

    expect(Activity::where('subject_type', Customer::class)->count())->toBe(0);
});

it('returns no_customer_match when drishti_client_id is unknown', function () {
    drishtiEvent(['clientId' => 'unknown-id', 'postId' => 'p1'], 'post.approved')
        ->assertOk()
        ->assertJson(['status' => 'no_customer_match']);

    expect(Activity::where('subject_type', Customer::class)->count())->toBe(0);
});

it('matches the customer by drishti_client_id', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-xyz']);

    drishtiEvent(['clientId' => 'drishti-xyz', 'postId' => 'p1'], 'post.approved')
        ->assertJson(['status' => 'ok']);

    expect(Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->where('event', 'updated')->count())->toBe(1);
});

// ─── Event handling ───────────────────────────────────────────────────────────

it('ignores unknown events without creating an activity', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(['clientId' => 'drishti-abc'], 'audit.completed')
        ->assertOk()
        ->assertJson(['status' => 'ignored', 'reason' => 'unknown_event']);

    expect(Activity::where('subject_type', Customer::class)->where('event', 'updated')->count())->toBe(0);
});

it('creates an activity for post.approved with post id and platforms', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(
        ['clientId' => 'drishti-abc', 'postId' => 'post-001', 'platforms' => ['INSTAGRAM', 'FACEBOOK']],
        'post.approved',
    )->assertJson(['status' => 'ok']);

    $activity = Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->where('event', 'updated')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->changes['drishti_event'])->toBe('post.approved')
        ->and($activity->changes['post_id'])->toBe('post-001')
        ->and($activity->changes['platforms'])->toContain('INSTAGRAM');
});

it('creates an activity for post.rejected with post id', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(
        ['clientId' => 'drishti-abc', 'postId' => 'post-002'],
        'post.rejected',
    )->assertJson(['status' => 'ok']);

    $activity = Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->where('event', 'updated')->first();
    expect($activity->changes['drishti_event'])->toBe('post.rejected')
        ->and($activity->changes['post_id'])->toBe('post-002')
        ->and($activity->changes)->not->toHaveKey('platforms');
});

it('creates an activity for post.published with platforms', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(
        ['clientId' => 'drishti-abc', 'postId' => 'post-003', 'platforms' => ['LINKEDIN']],
        'post.published',
    )->assertJson(['status' => 'ok']);

    $activity = Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->where('event', 'updated')->first();
    expect($activity->changes['drishti_event'])->toBe('post.published')
        ->and($activity->changes['platforms'])->toContain('LINKEDIN');
});

it('records the activity with user_id null (system event)', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    drishtiEvent(['clientId' => 'drishti-abc', 'postId' => 'p1'], 'post.approved');

    expect(Activity::latest()->first()->user_id)->toBeNull();
});
