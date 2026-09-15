<?php

use App\Actions\FlagPossibleDuplicateLead;
use App\Enums\LeadSource;
use App\Enums\UserRole;
use App\Jobs\MuteWadeskConversationJob;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\PossibleDuplicateLeadNotification;
use App\Services\DuplicateLeadDetector;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.whatsapp_webhook.token' => 'test-wa-token']);
    Queue::fake(); // keep LeadObserver's own auto-assign/scoring side effects inert
    Notification::fake();
});

// ──────────────────────────────────────────────────────────────────────────
// DuplicateLeadDetector — window/phone-exclusion behavior (needs real DB
// timestamps, so lives here rather than the pure-logic Unit test)
// ──────────────────────────────────────────────────────────────────────────

it('finds an older, similarly-named lead created within the 14-day window', function () {
    $older = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subDays(5),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Dr Rahul jain',
        'phone' => '919011847108',
        'source' => LeadSource::Whatsapp,
        'created_at' => now(),
    ]);

    $candidate = app(DuplicateLeadDetector::class)->findCandidate($newLead);

    expect($candidate)->not->toBeNull()->id->toBe($older->id);
});

it('does not match a similar name from 15+ days ago — outside the 14-day window', function () {
    Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subDays(15),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '919011847108',
        'source' => LeadSource::Whatsapp,
        'created_at' => now(),
    ]);

    expect(app(DuplicateLeadDetector::class)->findCandidate($newLead))->toBeNull();
});

it('matches at exactly the 14-day boundary', function () {
    $older = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subDays(14),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '919011847108',
        'source' => LeadSource::Whatsapp,
        'created_at' => now(),
    ]);

    expect(app(DuplicateLeadDetector::class)->findCandidate($newLead)->id)->toBe($older->id);
});

it('excludes a lead with the exact same phone number as the new lead', function () {
    Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '919011847108',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subDay(),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '919011847108',
        'source' => LeadSource::Whatsapp,
        'created_at' => now(),
    ]);

    expect(app(DuplicateLeadDetector::class)->findCandidate($newLead))->toBeNull();
});

it('never matches two leads both carrying the generic "WhatsApp Inquiry" fallback name — real false-positive shape found via a 2026-09-15 production dry-run', function () {
    Lead::factory()->create([
        'name' => 'WhatsApp Inquiry',
        'phone' => '919999999998',
        'created_at' => now()->subDay(),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'WhatsApp Inquiry',
        'phone' => '919011847108',
        'created_at' => now(),
    ]);

    expect(app(DuplicateLeadDetector::class)->findCandidate($newLead))->toBeNull();
});

it('a genuinely unnamed lead created via the real webhook path never flags against another unnamed lead', function () {
    User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    Lead::factory()->create([
        'name' => 'WhatsApp Inquiry',
        'phone' => '919999999998',
        'source' => LeadSource::Whatsapp,
        'created_at' => now()->subDay(),
    ]);

    // No contact_name in the payload -- handleUnmatchedNumber() falls back
    // to the same literal "WhatsApp Inquiry" string.
    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'message' => 'Hello',
        'conversation_id' => 'conv_no_name_no_flag',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $lead = Lead::where('whatsapp_conversation_id', 'conv_no_name_no_flag')->firstOrFail();
    expect($lead->name)->toBe('WhatsApp Inquiry')
        ->and($lead->possible_duplicate_of_lead_id)->toBeNull();
    Notification::assertNothingSent();
});

it('returns null when the new lead has no similar-name candidate at all', function () {
    Lead::factory()->create([
        'name' => 'Priya Shah',
        'phone' => '919999999998',
        'created_at' => now()->subDay(),
    ]);
    $newLead = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '919011847108',
        'created_at' => now(),
    ]);

    expect(app(DuplicateLeadDetector::class)->findCandidate($newLead))->toBeNull();
});

// ──────────────────────────────────────────────────────────────────────────
// Full webhook flow — flag + notify
// ──────────────────────────────────────────────────────────────────────────

it('flags a possible duplicate and notifies active Admin/Manager when handleUnmatchedNumber() creates a new lead matching a recent one', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    $manager = User::factory()->create(['role' => UserRole::Manager, 'is_active' => true]);
    $olderLead = Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subMinutes(1),
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Dr Rahul jain',
        'message' => 'Hello, I filled your form',
        'conversation_id' => 'conv_possible_dup',
    ], ['Authorization' => 'Bearer test-wa-token'])
        ->assertOk()
        ->assertJson(['status' => 'lead_created']);

    $newLead = Lead::where('whatsapp_conversation_id', 'conv_possible_dup')->firstOrFail();

    expect($newLead->possible_duplicate_of_lead_id)->toBe($olderLead->id)
        ->and($newLead->duplicate_flagged_at)->not->toBeNull();

    Notification::assertSentTo(
        $admin,
        PossibleDuplicateLeadNotification::class,
        fn ($n) => $n->newLead->is($newLead) && $n->olderLead->is($olderLead)
            && str_contains($n->toArray($admin)['message'], 'Rahul Jain')
            && str_contains($n->toArray($admin)['message'], $olderLead->phone)
            && $n->toArray($admin)['url'] === route('leads.merge.show', ['ids' => [$olderLead->id, $newLead->id]]),
    );
    Notification::assertSentTo($manager, PossibleDuplicateLeadNotification::class);

    Queue::assertPushed(MuteWadeskConversationJob::class, fn ($job) => $job->conversationId === 'conv_possible_dup');
});

it('does not notify a Sales rep or an inactive Admin', function () {
    User::factory()->create(['role' => UserRole::Sales, 'is_active' => true]);
    $inactiveAdmin = User::factory()->create(['role' => UserRole::Admin, 'is_active' => false]);
    Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subMinutes(1),
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Dr Rahul jain',
        'message' => 'Hello',
        'conversation_id' => 'conv_no_notify_wrong_role',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    Notification::assertNotSentTo($inactiveAdmin, PossibleDuplicateLeadNotification::class);
});

it('does not flag or notify when no similar-name lead exists', function () {
    User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    Lead::factory()->create(['name' => 'Priya Shah', 'phone' => '919999999998']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Totally Unrelated Person',
        'message' => 'Hello',
        'conversation_id' => 'conv_no_match',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $lead = Lead::where('whatsapp_conversation_id', 'conv_no_match')->firstOrFail();
    expect($lead->possible_duplicate_of_lead_id)->toBeNull()
        ->and($lead->duplicate_flagged_at)->toBeNull();

    Notification::assertNothingSent();
});

it('sends exactly one notification, not one per subsequent message in the same (already-flagged) conversation', function () {
    User::factory()->create(['role' => UserRole::Admin, 'is_active' => true]);
    Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subMinutes(1),
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Dr Rahul jain',
        'message' => 'First message',
        'conversation_id' => 'conv_repeat_no_renotify',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'lead_created']);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Dr Rahul jain',
        'message' => 'Second message, same conversation',
        'conversation_id' => 'conv_repeat_no_renotify',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertJson(['status' => 'lead_note_added']);

    Notification::assertSentTimes(PossibleDuplicateLeadNotification::class, 1);
});

it('the FlagPossibleDuplicateLead action itself is a no-op when the lead is already flagged (defensive, direct guard test)', function () {
    $olderLead = Lead::factory()->create(['name' => 'Rahul Jain', 'phone' => '+917775817080', 'created_at' => now()->subDay()]);
    $lead = Lead::factory()->create([
        'name' => 'Dr Rahul jain',
        'phone' => '919011847108',
        'possible_duplicate_of_lead_id' => $olderLead->id,
        'duplicate_flagged_at' => now(),
    ]);

    app(FlagPossibleDuplicateLead::class)->handle($lead);

    Notification::assertNothingSent();
});

it('a genuinely unrelated new lead created via the webhook is never flagged even when an older lead exists outside the window', function () {
    Lead::factory()->create([
        'name' => 'Rahul Jain',
        'phone' => '+917775817080',
        'source' => LeadSource::MetaAds,
        'created_at' => now()->subDays(20),
    ]);

    $this->postJson('/api/webhook/whatsapp', [
        'phone' => '919011847108',
        'contact_name' => 'Dr Rahul jain',
        'message' => 'Hello',
        'conversation_id' => 'conv_outside_window',
    ], ['Authorization' => 'Bearer test-wa-token'])->assertOk();

    $lead = Lead::where('whatsapp_conversation_id', 'conv_outside_window')->firstOrFail();
    expect($lead->possible_duplicate_of_lead_id)->toBeNull();
});

it('does not dispatch a mute job when the flagged lead has no whatsapp_conversation_id', function () {
    $olderLead = Lead::factory()->create(['name' => 'Rahul Jain', 'phone' => '+917775817080', 'created_at' => now()->subDay()]);
    $lead = Lead::factory()->create(['name' => 'Dr Rahul jain', 'phone' => '919011847108', 'whatsapp_conversation_id' => null]);

    app(FlagPossibleDuplicateLead::class)->handle($lead);

    expect($lead->fresh()->possible_duplicate_of_lead_id)->toBe($olderLead->id);
    Queue::assertNotPushed(MuteWadeskConversationJob::class);
});
