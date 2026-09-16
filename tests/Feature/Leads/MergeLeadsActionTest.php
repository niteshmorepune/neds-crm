<?php

use App\Actions\MergeLeads;
use App\Enums\LeadBudgetRange;
use App\Enums\LeadGoal;
use App\Enums\LeadStatus;
use App\Enums\VisibilityAuditFunnelEventType;
use App\Enums\VisibilityAuditTier;
use App\Enums\VisibilityAuditTouchChannel;
use App\Enums\VisibilityAuditTouchType;
use App\Jobs\SendOfferRecommendationReadyJob;
use App\Models\Activity;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\LeadWhatsappConversation;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\User;
use App\Models\VisibilityAuditFunnelEvent;
use App\Models\VisibilityAuditPurchase;
use App\Models\VisibilityAuditTouch;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

it('applies the given field values to the primary lead', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['name' => 'Old name', 'phone' => '111']);
    $duplicate = Lead::factory()->create(['name' => 'New name', 'phone' => '222']);

    $merged = app(MergeLeads::class)->handle($primary, $duplicate, ['name' => 'New name', 'phone' => '111']);

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

    app(MergeLeads::class)->handle($primary, $duplicate, []);

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

    app(MergeLeads::class)->handle($primary, $duplicate, []);

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

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    // The duplicate's own soft-delete (at the end of handle()) legitimately
    // logs a fresh "deleted" event under its own id — that's real, accurate
    // history, not something to reassign. Its "created" event, reassigned
    // BEFORE the delete, is what should have moved onto the primary.
    expect(Activity::where('subject_type', Lead::class)->where('subject_id', $duplicate->id)->where('event', 'created')->exists())->toBeFalse()
        ->and(Activity::where('subject_type', Lead::class)->where('subject_id', $duplicate->id)->where('event', 'deleted')->exists())->toBeTrue()
        ->and(Activity::where('subject_type', Lead::class)->where('subject_id', $primary->id)->where('event', 'created')->exists())->toBeTrue();
});

it('carries the duplicate\'s whatsapp_conversation_id onto the primary lead when the primary has none', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => null]);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'wa_conv_123']);

    $merged = app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect($merged->whatsapp_conversation_id)->toBe('wa_conv_123');
});

it('does not overwrite the primary lead\'s own whatsapp_conversation_id with the duplicate\'s', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'primary_conv']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'duplicate_conv']);

    $merged = app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect($merged->whatsapp_conversation_id)->toBe('primary_conv');
});

it('records the duplicate\'s own conversation_id as an additional mapping onto the primary when both leads have their own — real incident 2026-09-16, #421/#420 and #422/#423', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'primary_conv']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'duplicate_conv']);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    $mapping = LeadWhatsappConversation::where('conversation_id', 'duplicate_conv')->first();
    expect($mapping)->not->toBeNull()
        ->and($mapping->lead_id)->toBe($primary->id)
        // The primary's own column is untouched — this is an ADDITIONAL
        // mapping, not a replacement.
        ->and($primary->fresh()->whatsapp_conversation_id)->toBe('primary_conv');
});

it('does not create a mapping row when only one of the two leads has a conversation_id — no regression on the simple case', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['whatsapp_conversation_id' => null]);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'duplicate_conv']);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect(LeadWhatsappConversation::count())->toBe(0);
});

it('does not throw and does not duplicate the row when a mapping for that conversation_id already exists (firstOrCreate, defensive)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $existingOwner = Lead::factory()->create();
    LeadWhatsappConversation::create(['lead_id' => $existingOwner->id, 'conversation_id' => 'already_mapped_conv']);

    $primary = Lead::factory()->create(['whatsapp_conversation_id' => 'primary_conv']);
    $duplicate = Lead::factory()->create(['whatsapp_conversation_id' => 'already_mapped_conv']);

    expect(fn () => app(MergeLeads::class)->handle($primary, $duplicate, []))->not->toThrow(Throwable::class);
    expect(LeadWhatsappConversation::where('conversation_id', 'already_mapped_conv')->count())->toBe(1)
        ->and(LeadWhatsappConversation::where('conversation_id', 'already_mapped_conv')->first()->lead_id)->toBe($existingOwner->id);
});

it('leaves a breadcrumb note on the primary lead naming the merged-in duplicate', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create(['name' => 'Ganesh Auto Parts']);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect($primary->notes()->where('body', 'like', '%Ganesh Auto Parts%')->exists())->toBeTrue();
});

it('soft-deletes the duplicate lead, leaving it recoverable', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect(Lead::find($duplicate->id))->toBeNull();
    $this->assertSoftDeleted($duplicate);
});

it('leaves the primary lead active and untouched by soft-delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['status' => LeadStatus::Qualified]);
    $duplicate = Lead::factory()->create();

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect(Lead::find($primary->id))->not->toBeNull()
        ->and($primary->fresh()->trashed())->toBeFalse();
});

it('throws when asked to merge a lead with itself', function () {
    $lead = Lead::factory()->create();

    expect(fn () => app(MergeLeads::class)->handle($lead, $lead, []))
        ->toThrow(RuntimeException::class);
});

it('rolls back everything if the transaction fails partway', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create();
    $duplicate = Lead::factory()->create();
    $note = $duplicate->notes()->create(['user_id' => $user->id, 'body' => 'x']);

    expect(fn () => app(MergeLeads::class)->handle($primary, $duplicate, ['status' => 'not-a-real-status']))
        ->toThrow(ValueError::class);

    // Nothing committed — note still on the duplicate, duplicate still active.
    expect($note->fresh()->notable_id)->toBe($duplicate->id);
    expect(Lead::find($duplicate->id))->not->toBeNull();
});

// --- CarryOverMergeFields: real business-data fields MERGEABLE_FIELDS never offered a choice on ---

it('carries over every non-unique real-business-data field the duplicate has when the primary lacks it', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $telecaller = User::factory()->create();
    $primary = Lead::factory()->create([
        'goal' => null, 'budget_range' => null, 'website_url' => null, 'gbp_url' => null,
        'utm_source' => null, 'utm_medium' => null, 'utm_campaign' => null,
        'next_follow_up_at' => null, 'telecaller_id' => null,
    ]);
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'website_url' => 'https://example.com', 'gbp_url' => 'https://maps.example.com/x',
        'utm_source' => 'meta', 'utm_medium' => 'paid_social', 'utm_campaign' => 'campaign_123',
        'next_follow_up_at' => now()->addDays(2), 'telecaller_id' => $telecaller->id,
    ]);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    $primary->refresh();
    expect($primary->goal)->toBe(LeadGoal::GenerateLeads)
        ->and($primary->budget_range)->toBe(LeadBudgetRange::Under3000)
        ->and($primary->website_url)->toBe('https://example.com')
        ->and($primary->gbp_url)->toBe('https://maps.example.com/x')
        ->and($primary->utm_source)->toBe('meta')
        ->and($primary->utm_medium)->toBe('paid_social')
        ->and($primary->utm_campaign)->toBe('campaign_123')
        ->and($primary->next_follow_up_at)->not->toBeNull()
        ->and($primary->telecaller_id)->toBe($telecaller->id);
});

it('never overwrites the primary\'s own existing value for any of the non-unique carry-over fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $primaryTelecaller = User::factory()->create();
    $duplicateTelecaller = User::factory()->create();
    $primary = Lead::factory()->create([
        'goal' => LeadGoal::RankHigher, 'budget_range' => LeadBudgetRange::TwelvePlus,
        'website_url' => 'https://primary.example.com', 'gbp_url' => 'https://maps.example.com/primary',
        'utm_source' => 'primary-source', 'utm_medium' => 'primary-medium', 'utm_campaign' => 'primary-campaign',
        'next_follow_up_at' => now()->addDay(), 'telecaller_id' => $primaryTelecaller->id,
    ]);
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'website_url' => 'https://duplicate.example.com', 'gbp_url' => 'https://maps.example.com/duplicate',
        'utm_source' => 'dup-source', 'utm_medium' => 'dup-medium', 'utm_campaign' => 'dup-campaign',
        'next_follow_up_at' => now()->addDays(5), 'telecaller_id' => $duplicateTelecaller->id,
    ]);
    $originalNextFollowUp = $primary->next_follow_up_at;

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    $primary->refresh();
    expect($primary->goal)->toBe(LeadGoal::RankHigher)
        ->and($primary->budget_range)->toBe(LeadBudgetRange::TwelvePlus)
        ->and($primary->website_url)->toBe('https://primary.example.com')
        ->and($primary->gbp_url)->toBe('https://maps.example.com/primary')
        ->and($primary->utm_source)->toBe('primary-source')
        ->and($primary->utm_medium)->toBe('primary-medium')
        ->and($primary->utm_campaign)->toBe('primary-campaign')
        ->and($primary->next_follow_up_at->equalTo($originalNextFollowUp))->toBeTrue()
        ->and($primary->telecaller_id)->toBe($primaryTelecaller->id);
});

it('carries meta_leadgen_id onto the primary when it has none, freeing the duplicate\'s unique slot first', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['meta_leadgen_id' => null]);
    $duplicate = Lead::factory()->create(['meta_leadgen_id' => 'lg_'.Str::random(10)]);
    $metaLeadgenId = $duplicate->meta_leadgen_id;

    expect(fn () => app(MergeLeads::class)->handle($primary, $duplicate, []))->not->toThrow(Throwable::class);

    expect($primary->fresh()->meta_leadgen_id)->toBe($metaLeadgenId);
});

it('does not overwrite the primary\'s own meta_leadgen_id with the duplicate\'s', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['meta_leadgen_id' => 'lg_primary']);
    $duplicate = Lead::factory()->create(['meta_leadgen_id' => 'lg_duplicate']);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    expect($primary->fresh()->meta_leadgen_id)->toBe('lg_primary');
    // The duplicate's own value is left as-is (never nulled) since it was
    // never actually moved — only the "primary gets it, duplicate is
    // cleared" path clears the duplicate's column.
    expect($duplicate->fresh()->meta_leadgen_id)->toBe('lg_duplicate');
});

it('carries the full recommendation bundle onto the primary when it has no token of its own, freeing the duplicate\'s unique slot first', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $generatedAt = now()->subDay();
    $primary = Lead::factory()->create([
        'goal' => null, 'budget_range' => null,
        'recommendation_key' => null, 'recommendation_offer_key' => null,
        'recommendation_token' => null, 'recommendation_generated_at' => null,
    ]);
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit',
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'recommendation_generated_at' => $generatedAt,
    ]);
    $duplicateToken = $duplicate->recommendation_token;

    expect(fn () => app(MergeLeads::class)->handle($primary, $duplicate, []))->not->toThrow(Throwable::class);

    $primary->refresh();
    expect($primary->recommendation_key)->toBe('lead-generation-audit')
        ->and($primary->recommendation_offer_key)->toBe('lead_generation_audit')
        ->and($primary->recommendation_token)->toBe($duplicateToken)
        // Second-precision comparison -- the DB column truncates the
        // microseconds now()->subDay() carries in-memory.
        ->and($primary->recommendation_generated_at->format('Y-m-d H:i:s'))->toBe($generatedAt->format('Y-m-d H:i:s'));
});

it('does not touch the primary\'s own recommendation bundle when it already has its own token — the #346/#411 shape', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $primaryToken = (string) Str::uuid();
    $primary = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit',
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => $primaryToken,
        'recommendation_generated_at' => now()->subDays(3),
    ]);
    $duplicateToken = (string) Str::uuid();
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit',
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => $duplicateToken,
        'recommendation_generated_at' => now()->subDay(),
    ]);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    // Primary keeps its own, older token — the duplicate's own newer one
    // is left on the now-trashed record, genuinely unrecovered (no
    // equivalent of LeadWhatsappConversation's mapping table exists for a
    // second recommendation token).
    expect($primary->fresh()->recommendation_token)->toBe($primaryToken);
});

it('generates a recommendation once a carried-over goal+budget pair completes for the first time', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create(['goal' => null, 'budget_range' => LeadBudgetRange::Under3000, 'meta_leadgen_id' => 'lg_'.Str::random(10)]);
    $duplicate = Lead::factory()->create(['goal' => LeadGoal::GenerateLeads, 'budget_range' => null]);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    $primary->refresh();
    expect($primary->goal)->toBe(LeadGoal::GenerateLeads)
        ->and($primary->recommendation_key)->not->toBeNull()
        ->and($primary->recommendation_token)->not->toBeNull();
    Queue::assertPushed(SendOfferRecommendationReadyJob::class);
});

it('does not double-dispatch the first-touch job when the carried-over recommendation bundle is already resolved', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user);
    $primary = Lead::factory()->create([
        'goal' => null, 'budget_range' => null,
        'recommendation_key' => null, 'recommendation_offer_key' => null,
        'recommendation_token' => null, 'recommendation_generated_at' => null,
        'meta_leadgen_id' => 'lg_'.Str::random(10),
    ]);
    $duplicate = Lead::factory()->create([
        'goal' => LeadGoal::GenerateLeads, 'budget_range' => LeadBudgetRange::Under3000,
        'recommendation_key' => 'lead-generation-audit',
        'recommendation_offer_key' => 'lead_generation_audit',
        'recommendation_token' => (string) Str::uuid(),
        'recommendation_generated_at' => now()->subDay(),
    ]);

    app(MergeLeads::class)->handle($primary, $duplicate, []);

    // The carried-over bundle already matches the matrix's own resolution
    // for generate_leads+under_3000, so GenerateLeadRecommendation::handle()
    // finds nothing dirty and never dispatches a fresh "recommendation
    // ready" message for data that was already resolved before the merge.
    Queue::assertNotPushed(SendOfferRecommendationReadyJob::class);
});
