<?php

use App\Enums\ContentStatus;
use App\Enums\ContentWorkflowType;
use App\Enums\UserRole;
use App\Models\ContentPiece;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;

beforeEach(function () {
    config(['services.smdost.service_key' => 'test-smdost-key']);
});

$headers = fn () => ['Authorization' => 'Bearer test-smdost-key'];

it('creates a neds_led content piece when copy is ready', function () use (&$headers) {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-001']);
    Project::factory()->create(['customer_id' => $customer->id, 'owner_id' => $user->id, 'status' => 'active']);

    $this->postJson('/api/webhooks/smdost/content-ready', [
        'smdost_client_id' => 'smd-001',
        'smdost_content_id' => 'cnt-abc-123',
        'platform' => 'instagram',
        'title' => 'Festival campaign post',
        'copy_text' => 'Celebrate the festival with our exclusive offer!',
        'publish_date' => '2026-07-15',
    ], $headers())
        ->assertCreated()
        ->assertJsonPath('status', 'created');

    $piece = ContentPiece::where('smdost_content_id', 'cnt-abc-123')->first();
    expect($piece)->not->toBeNull();
    expect($piece->workflow_type)->toBe(ContentWorkflowType::NedsLed);
    expect($piece->status)->toBe(ContentStatus::SentToPartner);
    expect($piece->copy_text)->toBe('Celebrate the festival with our exclusive offer!');
    expect($piece->publish_date->format('Y-m-d'))->toBe('2026-07-15');
});

it('is idempotent — duplicate fires return existing piece without creating a second', function () use (&$headers) {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-002']);
    Project::factory()->create(['customer_id' => $customer->id, 'owner_id' => $user->id, 'status' => 'active']);

    $payload = [
        'smdost_client_id' => 'smd-002',
        'smdost_content_id' => 'cnt-dup-999',
        'platform' => 'facebook',
        'title' => 'Promo post',
        'copy_text' => 'Check out our latest offers.',
    ];

    $this->postJson('/api/webhooks/smdost/content-ready', $payload, $headers())->assertCreated();
    $this->postJson('/api/webhooks/smdost/content-ready', $payload, $headers())->assertOk()
        ->assertJsonPath('status', 'already_exists');

    expect(ContentPiece::where('smdost_content_id', 'cnt-dup-999')->count())->toBe(1);
});

it('returns no_customer_match when smdost_client_id is unknown', function () use (&$headers) {
    $this->postJson('/api/webhooks/smdost/content-ready', [
        'smdost_client_id' => 'smd-unknown',
        'smdost_content_id' => 'cnt-xyz',
        'platform' => 'instagram',
        'title' => 'Test',
        'copy_text' => 'Body text.',
    ], $headers())
        ->assertOk()
        ->assertJsonPath('status', 'no_customer_match');
});

it('returns 422 when customer has no active project', function () use (&$headers) {
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-noproj']);

    $this->postJson('/api/webhooks/smdost/content-ready', [
        'smdost_client_id' => 'smd-noproj',
        'smdost_content_id' => 'cnt-noproj',
        'platform' => 'linkedin',
        'title' => 'Test',
        'copy_text' => 'Body text.',
    ], $headers())
        ->assertUnprocessable()
        ->assertJsonPath('status', 'no_project_found');
});

it('maps google business platform string to google_business enum', function () use (&$headers) {
    $user = User::factory()->create(['role' => UserRole::Manager]);
    $customer = Customer::factory()->create(['smdost_client_id' => 'smd-003']);
    Project::factory()->create(['customer_id' => $customer->id, 'owner_id' => $user->id, 'status' => 'active']);

    $this->postJson('/api/webhooks/smdost/content-ready', [
        'smdost_client_id' => 'smd-003',
        'smdost_content_id' => 'cnt-gmb-1',
        'platform' => 'Google Business',
        'title' => 'GMB post',
        'copy_text' => 'Visit our store today.',
    ], $headers());

    expect(ContentPiece::where('smdost_content_id', 'cnt-gmb-1')->first()->platform->value)->toBe('google_business');
});

it('rejects request with wrong service key', function () {
    $this->postJson('/api/webhooks/smdost/content-ready', [
        'smdost_client_id' => 'smd-001',
        'smdost_content_id' => 'cnt-bad',
        'platform' => 'instagram',
        'title' => 'Test',
        'copy_text' => 'Test.',
    ], ['Authorization' => 'Bearer wrong-key'])
        ->assertUnauthorized();
});
