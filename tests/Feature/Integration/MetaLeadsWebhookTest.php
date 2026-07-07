<?php

use App\Jobs\ImportMetaLead;
use Illuminate\Support\Facades\Bus;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    config(['services.meta.app_secret' => 'test-meta-app-secret', 'services.meta.webhook_verify_token' => 'test-verify-token']);
});

/**
 * Build a signed Meta webhook POST request. Computes the HMAC on the exact
 * JSON bytes that will arrive at the middleware.
 */
function metaLeadEvent(array $payload, string $secret = 'test-meta-app-secret'): TestResponse
{
    $body = json_encode($payload);
    $sig = 'sha256='.hash_hmac('sha256', $body, $secret);

    return test()->call(
        'POST',
        '/api/webhooks/meta-leads',
        [], [], [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_X-Hub-Signature-256' => $sig],
        $body,
    );
}

function metaLeadgenEntry(string $leadgenId): array
{
    return [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-123',
            'time' => now()->timestamp,
            'changes' => [[
                'field' => 'leadgen',
                'value' => ['leadgen_id' => $leadgenId, 'page_id' => 'page-123', 'form_id' => 'form-1', 'ad_id' => 'ad-1'],
            ]],
        ]],
    ];
}

// ─── Verification handshake (GET) ──────────────────────────────────────────

it('echoes hub_challenge when the verify token matches', function () {
    $this->get('/api/webhooks/meta-leads?hub_mode=subscribe&hub_verify_token=test-verify-token&hub_challenge=1234567')
        ->assertOk()
        ->assertSee('1234567');
});

it('rejects the handshake when the verify token is wrong', function () {
    $this->get('/api/webhooks/meta-leads?hub_mode=subscribe&hub_verify_token=wrong&hub_challenge=1234567')
        ->assertStatus(403);
});

it('rejects the handshake when hub_mode is not subscribe', function () {
    $this->get('/api/webhooks/meta-leads?hub_mode=unsubscribe&hub_verify_token=test-verify-token&hub_challenge=1234567')
        ->assertStatus(403);
});

// ─── Event authentication (POST) ───────────────────────────────────────────

it('rejects an event with no signature header', function () {
    $this->postJson('/api/webhooks/meta-leads', metaLeadgenEntry('lg-1'))->assertStatus(401);
});

it('rejects an event with a wrong signature', function () {
    metaLeadEvent(metaLeadgenEntry('lg-1'), 'wrong-secret')->assertStatus(401);
});

it('accepts an event with a correct signature', function () {
    Bus::fake();

    metaLeadEvent(metaLeadgenEntry('lg-1'))->assertOk()->assertJson(['status' => 'ok', 'dispatched' => 1]);
});

// ─── Event handling (POST) ─────────────────────────────────────────────────

it('dispatches ImportMetaLead for each leadgen change', function () {
    Bus::fake();

    metaLeadEvent(metaLeadgenEntry('lg-abc'));

    Bus::assertDispatched(ImportMetaLead::class, fn ($job) => $job->leadgenId === 'lg-abc');
});

it('dispatches a job per change when multiple leads arrive in one batch', function () {
    Bus::fake();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-123',
            'changes' => [
                ['field' => 'leadgen', 'value' => ['leadgen_id' => 'lg-1']],
                ['field' => 'leadgen', 'value' => ['leadgen_id' => 'lg-2']],
            ],
        ]],
    ];

    metaLeadEvent($payload)->assertJson(['status' => 'ok', 'dispatched' => 2]);

    Bus::assertDispatched(ImportMetaLead::class, 2);
});

it('ignores changes for fields other than leadgen', function () {
    Bus::fake();

    $payload = [
        'object' => 'page',
        'entry' => [[
            'id' => 'page-123',
            'changes' => [['field' => 'feed', 'value' => ['item' => 'post']]],
        ]],
    ];

    metaLeadEvent($payload)->assertJson(['status' => 'ok', 'dispatched' => 0]);

    Bus::assertNotDispatched(ImportMetaLead::class);
});
