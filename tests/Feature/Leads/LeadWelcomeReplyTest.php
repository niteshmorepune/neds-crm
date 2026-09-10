<?php

use App\Enums\CallOutcome;
use App\Enums\LeadStatus;
use App\Models\CallLog;
use App\Models\Lead;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    // See SendLeadWelcomeFollowUpsTest -- keeps LeadObserver's own
    // dispatches (SendLeadWelcomeMessageJob etc) from actually running.
    Queue::fake();
});

it('is not awaiting a reply when no welcome message was ever sent', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => null]);

    expect($lead->isAwaitingWelcomeReply())->toBeFalse()
        ->and($lead->isOverdueForWelcomeReply())->toBeFalse();
});

it('is awaiting a reply once the welcome message is sent and nobody has said anything back', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(1)]);

    expect($lead->isAwaitingWelcomeReply())->toBeTrue();
});

it('is only "overdue" once WELCOME_FOLLOWUP_WAIT_HOURS have passed', function () {
    $fresh = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(1)]);
    $overdue = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1)]);

    expect($fresh->isAwaitingWelcomeReply())->toBeTrue()
        ->and($fresh->isOverdueForWelcomeReply())->toBeFalse()
        ->and($overdue->isOverdueForWelcomeReply())->toBeTrue();
});

it('is not awaiting a reply once the lead has genuinely replied', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1)]);
    $lead->notes()->create(['user_id' => null, 'body' => 'Sure, please call after 5pm']);

    expect($lead->isAwaitingWelcomeReply())->toBeFalse();
});

it('is not awaiting a reply once staff has replied over WhatsApp', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1)]);
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by Kiran Katte]\nHi, calling you now."]);

    expect($lead->isAwaitingWelcomeReply())->toBeFalse();
});

it('is not awaiting a reply once the after-hours AI has replied', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1)]);
    $lead->notes()->create(['user_id' => null, 'body' => "[Sent via WhatsApp by AI Assistant (auto-reply)]\nThanks, someone will call soon."]);

    expect($lead->isAwaitingWelcomeReply())->toBeFalse();
});

it('is not awaiting a reply once the lead is Converted or Lost', function () {
    $lead = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1), 'status' => LeadStatus::Lost]);

    expect($lead->isAwaitingWelcomeReply())->toBeFalse();
});

it('does not let its own welcome/check-in confirmation notes count as a reply', function () {
    $welcomed = Lead::factory()->create(['welcome_message_sent_at' => now()->subHours(Lead::WELCOME_FOLLOWUP_WAIT_HOURS + 1)]);
    $welcomed->notes()->create(['user_id' => null, 'body' => '✨ Automated welcome message sent via WhatsApp — asking when\'s a good time to call.']);
    $welcomed->notes()->create(['user_id' => null, 'body' => '✨ Re-engagement check-in sent via WhatsApp.']);

    expect($welcomed->fresh()->isAwaitingWelcomeReply())->toBeTrue();
});

it('does not let a delivery-failure marker note count as a reply either', function () {
    // WadeskMessageStatusController's own downgrade notes -- same user_id=null,
    // no-WHATSAPP_OUTBOUND_PREFIX shape as the success markers above.
    $lead = Lead::factory()->create(['welcome_message_sent_at' => null]);
    $lead->notes()->create(['user_id' => null, 'body' => '❌ Welcome WhatsApp message failed to deliver: some reason']);
    $lead->notes()->create(['user_id' => null, 'body' => '❌ Re-engagement check-in failed to deliver: some reason']);

    // welcome_message_sent_at is null (the failure downgrade clears it), so
    // isAwaitingWelcomeReply() itself reads false -- the real regression this
    // guards is outreachAttemptSummary()'s reply detection directly.
    expect($lead->fresh()->outreachAttemptSummary()['whatsapp_inbound_reply'])->toBeFalse();
});

it('does not break isUnresponsive() -- the internal marker notes are excluded there too', function () {
    // Regression guard for the outreachAttemptSummary() fix: a lead with 3
    // outbound WhatsApp attempts and a welcome-confirmation note (but no
    // real reply, no connected call) must still read as unresponsive --
    // the marker note must not be misread as a reply that suppresses this.
    $lead = Lead::factory()->create(['status' => LeadStatus::Contacted]);
    CallLog::factory()->count(3)->create([
        'callable_type' => Lead::class, 'callable_id' => $lead->id, 'outcome' => CallOutcome::NoAnswer,
    ]);
    $lead->notes()->create(['user_id' => null, 'body' => '✨ Automated welcome message sent via WhatsApp — asking when\'s a good time to call.']);

    expect($lead->fresh()->isUnresponsive())->toBeTrue();
});
