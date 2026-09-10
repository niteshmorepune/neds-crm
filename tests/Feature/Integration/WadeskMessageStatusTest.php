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

it('downgrades a matching lead welcome send to failed, reopening its welcome eligibility', function () {
    $lead = Lead::factory()->create([
        'welcome_message_sent_at' => now()->subMinutes(2),
        'welcome_message_wadesk_id' => 'wamsg_welcome_1',
    ]);
    $lead->notes()->create(['user_id' => null, 'body' => '✨ Automated welcome message sent via WhatsApp — asking when\'s a good time to call.']);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_welcome_1',
        'error_code' => 131049,
        'error_message' => 'This message was not delivered to maintain healthy ecosystem engagement.',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertOk()
        ->assertJson(['status' => 'lead_updated', 'lead_id' => $lead->id]);

    $lead->refresh();
    expect($lead->welcome_message_sent_at)->toBeNull()
        ->and($lead->welcome_message_wadesk_id)->toBeNull();
    expect($lead->notes()->orderByDesc('id')->first()->body)
        ->toContain('❌ Welcome WhatsApp message failed to deliver: This message was not delivered to maintain healthy ecosystem engagement.');
});

it('downgrades a matching lead check-in send to failed, clearing the 24h cooldown', function () {
    $lead = Lead::factory()->create([
        'last_checkin_sent_at' => now()->subMinutes(2),
        'checkin_wadesk_id' => 'wamsg_checkin_1',
    ]);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_checkin_1',
        'error_message' => 'This message was not delivered to maintain healthy ecosystem engagement.',
    ], ['Authorization' => 'Bearer wa-webhook-secret'])
        ->assertOk()
        ->assertJson(['status' => 'lead_updated', 'lead_id' => $lead->id]);

    $lead->refresh();
    expect($lead->last_checkin_sent_at)->toBeNull()
        ->and($lead->checkin_wadesk_id)->toBeNull();
    expect($lead->notes()->latest()->first()->body)
        ->toContain('❌ Re-engagement check-in failed to deliver');
});

it('checks VisibilityAuditTouch before Lead, but still no-ops if neither matches', function () {
    Lead::factory()->create(['welcome_message_wadesk_id' => 'wamsg_unrelated']);

    $this->postJson('/api/webhooks/wadesk/message-failed', [
        'message_id' => 'wamsg_totally_different',
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
