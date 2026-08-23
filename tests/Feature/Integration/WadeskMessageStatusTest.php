<?php

use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Lead;
use App\Models\VisibilityAuditTouch;

beforeEach(function () {
    config(['services.whatsapp_webhook.token' => 'wa-webhook-secret']);
});

it('downgrades a matching touch to failed when wadesk.in reports a later delivery failure', function () {
    $lead = Lead::factory()->create();
    $touch = VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
        'meta' => ['template' => 'va_first_invite', 'wadesk_message_id' => 'wamsg_abc'],
    ]);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_abc',
        'error_code' => 131049,
        'error_message' => 'This message was not delivered to maintain healthy ecosystem engagement.',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertOk()
        ->assertJson(['status' => 'touch_updated', 'touch_id' => $touch->id]);

    $touch->refresh();
    expect($touch->success)->toBeFalse()
        ->and($touch->meta['error'])->toBe('This message was not delivered to maintain healthy ecosystem engagement.')
        ->and($touch->meta['error_code'])->toBe(131049)
        ->and($touch->meta['template'])->toBe('va_first_invite'); // original meta preserved, not overwritten
});

it('no-ops cleanly when the reported message_id matches no touch', function () {
    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_unknown',
        'error_message' => 'some failure',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertOk()
        ->assertJson(['status' => 'no_matching_touch']);
});

it('never downgrades an unrelated touch with a different wadesk_message_id', function () {
    $lead = Lead::factory()->create();
    $touch = VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp,
        'occurred_at' => now(),
        'success' => true,
        'meta' => ['wadesk_message_id' => 'wamsg_other'],
    ]);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_different',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertOk()
        ->assertJson(['status' => 'no_matching_touch']);

    expect($touch->fresh()->success)->toBeTrue();
});

it('ignores a staff-call touch even if it somehow shared the same message_id value', function () {
    // channel scoping guard: only ai_whatsapp touches are ever matched.
    $lead = Lead::factory()->create();
    VisibilityAuditTouch::create([
        'lead_id' => $lead->id,
        'touch_type' => VisibilityAuditTouchType::ManualOutreach,
        'channel' => VisibilityAuditTouchChannel::StaffCall,
        'occurred_at' => now(),
        'success' => true,
        'meta' => ['wadesk_message_id' => 'wamsg_shared'],
    ]);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_shared',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertJson(['status' => 'no_matching_touch']);
});

it('rejects requests without the correct token', function () {
    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_abc',
    ])->assertUnauthorized();
});

it('requires message_id', function () {
    $this->postJson('/api/webhooks/wadesk/message-failed', [], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertStatus(422);
});
