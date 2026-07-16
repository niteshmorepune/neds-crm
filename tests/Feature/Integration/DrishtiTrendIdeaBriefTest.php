<?php

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.drishti.webhook_secret' => 'test-drishti-webhook-secret',
        'services.smdost.base_url' => 'https://smdost.test',
        'services.smdost.service_key' => 'test-smdost-key',
    ]);
});

/**
 * Build and send a signed Drishti trend-idea-brief request — same HMAC
 * scheme as the existing /webhooks/drishti/event route, but this endpoint's
 * body is a flat payload (no event/data wrapper).
 */
function trendIdeaBriefRequest(array $data, string $secret = 'test-drishti-webhook-secret', ?int $timestamp = null): \Illuminate\Testing\TestResponse
{
    $ts = $timestamp ?? now()->timestamp;
    $body = json_encode($data);
    $sig = 'sha256='.hash_hmac('sha256', "{$ts}.{$body}", $secret);

    return test()->call(
        'POST',
        '/api/webhooks/drishti/trend-idea-brief',
        [], [], [],
        [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Agency-Signature' => $sig,
            'HTTP_X-Agency-Timestamp' => (string) $ts,
        ],
        $body,
    );
}

function trendIdeaPayload(array $overrides = []): array
{
    return array_merge([
        'drishti_client_id' => 'drishti-abc',
        'content_idea_id' => 'idea-001',
        'title' => 'Monsoon offer carousel',
        'platform' => 'INSTAGRAM',
        'hook' => 'Beat the rain with our AMC offer',
        'outline' => 'Carousel: 3 slides on monsoon service tips.',
        'trend_rationale' => 'Monsoon season searches are spiking this week.',
        'source_refs' => ['https://trends.example.com/monsoon'],
    ], $overrides);
}

it('rejects requests with no signature headers', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    test()->postJson('/api/webhooks/drishti/trend-idea-brief', trendIdeaPayload())->assertStatus(401);
});

it('rejects a wrong signature', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc']);

    trendIdeaBriefRequest(trendIdeaPayload(), 'wrong-secret')->assertStatus(401);
});

it('returns no_customer_match when drishti_client_id is unknown', function () {
    trendIdeaBriefRequest(trendIdeaPayload(['drishti_client_id' => 'unknown']))
        ->assertStatus(404)
        ->assertJson(['status' => 'no_customer_match']);
});

it('returns not_linked_to_smdost when the customer has no smdost_client_id', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => null]);

    trendIdeaBriefRequest(trendIdeaPayload())
        ->assertStatus(422)
        ->assertJson(['status' => 'not_linked_to_smdost']);
});

it('forwards a brief to SMDost with the mapped platform name and brief details', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => 'smd-123']);
    Http::fake(['smdost.test/api/briefs' => Http::response(['id' => 'brief-999'], 200)]);

    trendIdeaBriefRequest(trendIdeaPayload())
        ->assertOk()
        ->assertJson(['status' => 'ok', 'brief_id' => 'brief-999']);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://smdost.test/api/briefs'
            && $request->hasHeader('X-Service-Key', 'test-smdost-key')
            && $request['clientId'] === 'smd-123'
            && $request['title'] === 'Monsoon offer carousel'
            && $request['platforms'][0]['platform'] === 'Instagram'
            && str_contains($request['campaignDescription'], 'Monsoon season searches')
            && str_contains($request['campaignDescription'], 'https://trends.example.com/monsoon');
    });
});

it('records an activity on the matched customer after a successful send', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => 'smd-123']);
    Http::fake(['smdost.test/api/briefs' => Http::response(['id' => 'brief-999'], 200)]);

    trendIdeaBriefRequest(trendIdeaPayload())->assertOk();

    $activity = Activity::where('subject_type', Customer::class)
        ->where('subject_id', $customer->id)
        ->where('event', 'smdost_brief_created')
        ->first();

    expect($activity)->not->toBeNull()
        ->and($activity->changes['source'])->toBe('drishti_trend_idea')
        ->and($activity->changes['content_idea_id'])->toBe('idea-001')
        ->and($activity->changes['brief_id'])->toBe('brief-999')
        ->and($activity->user_id)->toBeNull();
});

it('does not send the same idea to SMDost twice', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => 'smd-123']);
    Http::fake(['smdost.test/api/briefs' => Http::response(['id' => 'brief-999'], 200)]);

    trendIdeaBriefRequest(trendIdeaPayload())->assertJson(['status' => 'ok']);
    trendIdeaBriefRequest(trendIdeaPayload())->assertJson(['status' => 'already_sent']);

    Http::assertSentCount(1);
});

it('returns smdost_error and does not log an activity when SMDost rejects the brief', function () {
    $customer = Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => 'smd-123']);
    Http::fake(['smdost.test/api/briefs' => Http::response(['error' => 'bad request'], 422)]);

    trendIdeaBriefRequest(trendIdeaPayload())
        ->assertStatus(502)
        ->assertJson(['status' => 'smdost_error']);

    expect(Activity::where('subject_type', Customer::class)->where('subject_id', $customer->id)->where('event', 'smdost_brief_created')->count())->toBe(0);
});

it('maps an unrecognized platform through as-is', function () {
    Customer::factory()->create(['drishti_client_id' => 'drishti-abc', 'smdost_client_id' => 'smd-123']);
    Http::fake(['smdost.test/api/briefs' => Http::response(['id' => 'brief-999'], 200)]);

    trendIdeaBriefRequest(trendIdeaPayload(['platform' => 'PINTEREST']))->assertOk();

    Http::assertSent(fn ($request) => $request['platforms'][0]['platform'] === 'PINTEREST');
});
