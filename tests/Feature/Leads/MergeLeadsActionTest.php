<?php

use App\Actions\MergeLeads;
use App\Enums\LeadStatus;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;

it('applies the given field values to the primary lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['name' => 'Old name', 'phone' => '111']);
    $duplicate = Lead::factory()->create(['name' => 'New name', 'phone' => '222']);

    $merged = (new MergeLeads)->handle($primary, $duplicate, ['name' => 'New name', 'phone' => '111']);

    expect($merged->name)->toBe('New name')
        ->and($merged->phone)->toBe('111');
});

it('reassigns the duplicate\'s notes, call logs, and meetings onto the primary lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $note = $duplicate->notes()->create(['user_id' => $user->id, 'body' => 'Follow-up needed']);
    $call = CallLog::factory()->create(['callable_type' => Lead::class, 'callable_id' => $duplicate->id]);
    $meeting = Meeting::factory()->create(['meetable_type' => Lead::class, 'meetable_id' => $duplicate->id]);

    (new MergeLeads)->handle($primary, $duplicate, []);

    expect($note->fresh()->notable_id)->toBe($primary->id)
        ->and($note->fresh()->notable_type)->toBe(Lead::class)
        ->and($call->fresh()->callable_id)->toBe($primary->id)
        ->and($meeting->fresh()->meetable_id)->toBe($primary->id);
});

it('reassigns the duplicate\'s Visibility Audit purchase, touches, and funnel events onto the primary lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    $purchase = VisibilityAuditPurchase::create([
        'tier' => VisibilityAuditTier::Gbp, 'amount_paise' => 12000,
        'razorpay_payment_id' => 'pay_merge1', 'lead_id' => $duplicate->id,
    ]);
    $touch = VisibilityAuditTouch::create([
        'lead_id' => $duplicate->id, 'touch_type' => VisibilityAuditTouchType::FirstInvite,
        'channel' => VisibilityAuditTouchChannel::AiWhatsapp, 'occurred_at' => now(), 'success' => true,
    ]);
    $event = VisibilityAuditFunnelEvent::create([
        'event_type' => VisibilityAuditFunnelEventType::LandingViewed, 'lead_id' => $duplicate->id,
    ]);

    (new MergeLeads)->handle($primary, $duplicate, []);

    expect($purchase->fresh()->lead_id)->toBe($primary->id)
        ->and($touch->fresh()->lead_id)->toBe($primary->id)
        ->and($event->fresh()->lead_id)->toBe($primary->id);
});

it('reassigns the duplicate\'s activity history onto the primary lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    Activity::create([
        'user_id' => $user->id, 'subject_type' => Lead::class, 'subject_id' => $duplicate->id,
        'event' => 'created', 'changes' => [],
    ]);

    (new MergeLeads)->handle($primary, $duplicate, []);

    // The duplicate's own soft-delete (at the end of handle()) legitimately
    // logs a fresh "deleted" event under its own id — that's real, accurate
    // history, not something to reassign. Its "created" event, reassigned
    // BEFORE the delete, is what should have moved onto the primary.
    expect(Activity::where('subject_type', Lead::class)->where('subject_id', $duplicate->id)->where('event', 'created')->exists())->toBeFalse()
        ->and(Activity::where('subject_type', Lead::class)->where('subject_id', $duplicate->id)->where('event', 'deleted')->exists())->toBeTrue()
        ->and(Activity::where('subject_type', Lead::class)->where('subject_id', $primary->id)->where('event', 'created')->exists())->toBeTrue();
});

it('leaves a breadcrumb note on the primary lead naming the merged-in duplicate', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create(['name' => 'Ganesh Auto Parts']);

    (new MergeLeads)->handle($primary, $duplicate, []);

    expect($primary->notes()->where('body', 'like', '%Ganesh Auto Parts%')->exists())->toBeTrue();
});

it('soft-deletes the duplicate lead, leaving it recoverable', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    (new MergeLeads)->handle($primary, $duplicate, []);

    expect(Lead::find($duplicate->id))->toBeNull();
    $this->assertSoftDeleted($duplicate);
});

it('leaves the primary lead active and untouched by soft-delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['status' => LeadStatus::Qualified]);
    $duplicate = Lead::factory()->create();

    (new MergeLeads)->handle($primary, $duplicate, []);

    expect(Lead::find($primary->id))->not->toBeNull()
        ->and($primary->fresh()->trashed())->toBeFalse();
});

it('throws when asked to merge a lead with itself', function () {
    $lead = Lead::factory()->create();

    expect(fn () => (new MergeLeads)->handle($lead, $lead, []))
        ->toThrow(RuntimeException::class);
});

it('rolls back everything if the transaction fails partway', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();
    $note = $duplicate->notes()->create(['user_id' => $user->id, 'body' => 'x']);

    expect(fn () => (new MergeLeads)->handle($primary, $duplicate, ['status' => 'not-a-real-status']))
        ->toThrow(ValueError::class);

    // Nothing committed — note still on the duplicate, duplicate still active.
    expect($note->fresh()->notable_id)->toBe($duplicate->id);
    expect(Lead::find($duplicate->id))->not->toBeNull();
});
