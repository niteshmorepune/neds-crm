<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use App\Notifications\LeadEscalatedToManagerNotification;
use App\Notifications\LeadOwnerReminderNotification;
use Illuminate\Support\Facades\Notification;

/**
 * LogsActivity logs a 'created' Activity row at the REAL current time when
 * the factory saves the lead, regardless of a backdated `created_at` on the
 * lead itself — in production these always match (both happen at the same
 * real instant), so keep that true here too, otherwise the auto-logged
 * activity always reads as "just touched" and every test below would
 * silently exclude its own lead from the untouched query.
 */
function backdateLeadActivity(Lead $lead): void
{
    $lead->activities()->update(['created_at' => $lead->created_at]);
}

it('reminds the owner of a New lead untouched past the owner threshold', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(25)]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertSentTo($owner, LeadOwnerReminderNotification::class);
    expect($lead->fresh()->owner_reminder_sent_at)->not->toBeNull();
});

it('does not remind before the owner threshold has elapsed', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(5)]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($owner, LeadOwnerReminderNotification::class);
});

it('does not remind a lead that already has a note, even if otherwise untouched', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(25)]);
    backdateLeadActivity($lead);
    Note::factory()->for($lead, 'notable')->create(['created_at' => now()->subMinutes(2)]);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($owner, LeadOwnerReminderNotification::class);
});

it('does not remind a lead that already has a call logged', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(25)]);
    backdateLeadActivity($lead);
    CallLog::factory()->for($lead, 'callable')->create(['user_id' => $owner->id, 'called_at' => now()->subMinutes(2)]);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($owner, LeadOwnerReminderNotification::class);
});

it('does not remind a lead that has moved past New status', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::Contacted, 'created_at' => now()->subMinutes(25)]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($owner, LeadOwnerReminderNotification::class);
});

it('does not remind the same lead twice', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create([
        'status' => LeadStatus::New, 'created_at' => now()->subMinutes(25),
        'owner_reminder_sent_at' => now()->subMinutes(5),
    ]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($owner, LeadOwnerReminderNotification::class);
});

it('escalates to Admin/Manager once a lead is still untouched past the manager threshold', function () {
    Notification::fake();
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $inactiveManager = User::factory()->role(UserRole::Manager)->create(['is_active' => false]);
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create([
        'status' => LeadStatus::New, 'created_at' => now()->subMinutes(65),
        'owner_reminder_sent_at' => now()->subMinutes(45),
    ]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertSentTo($admin, LeadEscalatedToManagerNotification::class);
    Notification::assertSentTo($manager, LeadEscalatedToManagerNotification::class);
    Notification::assertNotSentTo($inactiveManager, LeadEscalatedToManagerNotification::class);
});

it('reminds the owner AND escalates to managers in the same run when a lead is already old enough for both thresholds', function () {
    // A gap in the scheduler (or a lead created well before escalation was
    // ever turned on) shouldn't make the manager wait an extra cycle just
    // because the owner reminder happens to fire in this same run —
    // remindOwners() sets owner_reminder_sent_at first, and
    // escalateToManagers() sees it immediately within the same invocation.
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(90)]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertSentTo($owner, LeadOwnerReminderNotification::class);
    Notification::assertSentTo($manager, LeadEscalatedToManagerNotification::class);
});

it('does not escalate a lead that is old enough for the manager threshold but too new for the owner threshold', function () {
    // Guards the two-stage gate itself: a lead can never reach escalation
    // without owner_reminder_sent_at being set, which only happens once the
    // lead has cleared the (shorter) owner threshold.
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::New, 'created_at' => now()->subMinutes(10)]);
    backdateLeadActivity($lead);

    // manager-minutes below owner-minutes is a contrived config, but proves
    // the gate: even if the manager cutoff alone would match this lead's
    // age, escalation still requires owner_reminder_sent_at to be non-null.
    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 5]);

    Notification::assertNotSentTo($manager, LeadEscalatedToManagerNotification::class);
});

it('does not escalate the same lead to managers twice', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create([
        'status' => LeadStatus::New, 'created_at' => now()->subMinutes(90),
        'owner_reminder_sent_at' => now()->subMinutes(70),
        'manager_escalated_at' => now()->subMinutes(5),
    ]);
    backdateLeadActivity($lead);

    $this->artisan('app:escalate-untouched-leads', ['--owner-minutes' => 20, '--manager-minutes' => 60]);

    Notification::assertNotSentTo($manager, LeadEscalatedToManagerNotification::class);
});
