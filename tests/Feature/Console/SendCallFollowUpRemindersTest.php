<?php

use App\Enums\LeadStatus;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\CallFollowUpDue;
use Illuminate\Support\Facades\Notification;

it('notifies the rep when a call follow-up is due', function () {
    Notification::fake();
    $user = User::factory()->create();
    $lead = Lead::factory()->create();
    $call = CallLog::factory()->create([
        'user_id' => $user->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:send-call-followup-reminders');

    Notification::assertSentTo($user, CallFollowUpDue::class);
    expect($call->fresh()->follow_up_notified_at)->not->toBeNull();
});

it('does not notify for a follow-up against a lead that has since been marked Lost', function () {
    Notification::fake();
    $user = User::factory()->create();
    $lead = Lead::factory()->create(['status' => LeadStatus::Lost]);
    $call = CallLog::factory()->create([
        'user_id' => $user->id,
        'callable_type' => Lead::class,
        'callable_id' => $lead->id,
        'follow_up_at' => now()->subMinutes(5),
    ]);

    $this->artisan('app:send-call-followup-reminders');

    // Not assertNothingSent() — creating a Lost-status Lead via the
    // factory still fires LeadObserver's own unrelated side effects
    // (e.g. NewLeadNotification); the point here is specifically that
    // THIS command's own notification didn't go out for it.
    Notification::assertNotSentTo($user, CallFollowUpDue::class);
    expect($call->fresh()->follow_up_notified_at)->toBeNull();
});
