<?php

use App\Enums\LeadGoal;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Models\CallLog;
use App\Models\Lead;
use App\Models\Note;
use App\Models\User;
use App\Notifications\LeadStagnationEscalatedNotification;
use App\Notifications\LeadWantsExpertAdviceNotification;
use App\Notifications\NotSureLeadEscalatedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/**
 * LeadObserver stamps notsure_at = now() the moment goal becomes NotSure
 * (see stampNotSureBaseline()), which overrides whatever the factory itself
 * was given -- backdate it afterward via a quiet save, same pattern as
 * EscalateUntouchedLeadsTest's own backdateLeadActivity() helper.
 */
function backdateNotSure(Lead $lead, Carbon $at): void
{
    $lead->forceFill(['notsure_at' => $at])->saveQuietly();
}

function notSureLead(User $owner, array $overrides = []): Lead
{
    return Lead::factory()->ownedBy($owner->id)->create(array_merge([
        'status' => LeadStatus::Contacted,
        'goal' => LeadGoal::NotSure,
    ], $overrides));
}

/**
 * LeadObserver::stampNotSureBaseline() resets notsure_owner_notified_at/
 * notsure_manager_escalated_at to null the moment goal becomes NotSure
 * (a fresh escalation cycle) -- so, like notsure_at itself, these can't be
 * set via the factory's own create() array; they must be stamped in a
 * separate quiet save afterward.
 */
function stampNotSureGuards(Lead $lead, array $fields): void
{
    $lead->forceFill($fields)->saveQuietly();
}

it('nags the owner once zero staff engagement passes the owner threshold', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(7));

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertSentTo(
        $owner,
        LeadWantsExpertAdviceNotification::class,
        fn ($n) => $n->lead->is($lead) && $n->isReminder === true,
    );
    expect($lead->fresh()->notsure_owner_notified_at)->not->toBeNull();
});

it('does not nag before the owner threshold has elapsed', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(2));

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
});

it('does not nag a lead that already had staff engagement since it went Not Sure', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(7));
    Note::factory()->for($lead, 'notable')->create(['user_id' => $owner->id, 'created_at' => now()->subHours(3)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
});

it('does not nag the same lead twice', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(7));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(1)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
});

it('does not nag a lead whose goal is not Not Sure, or that has no owner, or is Converted/Lost', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();

    Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::Contacted, 'goal' => LeadGoal::GrowBusiness]);

    // LeadObserver::autoAssign() round-robins any null owner_id onto an
    // already-existing eligible Sales user the moment this lead is created
    // (see [[feedback-gotchas]]) -- null it back out afterward via a quiet
    // save so this fixture stays genuinely unowned.
    $noOwner = Lead::factory()->create(['status' => LeadStatus::New, 'goal' => LeadGoal::NotSure]);
    $noOwner->forceFill(['owner_id' => null])->saveQuietly();
    backdateNotSure($noOwner, now()->subHours(7));
    $converted = notSureLead($owner, ['status' => LeadStatus::Converted]);
    backdateNotSure($converted, now()->subHours(7));
    $lost = notSureLead($owner, ['status' => LeadStatus::Lost]);
    backdateNotSure($lost, now()->subHours(7));

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    // NewLeadNotification always fires on Lead creation, unrelated to this
    // command -- only assert the reminder this command itself would send.
    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
});

it('escalates to Admin/Manager once a Not-Sure lead is still untouched past the manager threshold', function () {
    Notification::fake();
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $inactiveManager = User::factory()->role(UserRole::Manager)->create(['is_active' => false]);
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(24));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(18)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertSentTo($admin, NotSureLeadEscalatedNotification::class, fn ($n) => $n->lead->is($lead));
    Notification::assertSentTo($manager, NotSureLeadEscalatedNotification::class);
    Notification::assertNotSentTo($inactiveManager, NotSureLeadEscalatedNotification::class);
    expect($lead->fresh()->notsure_manager_escalated_at)->not->toBeNull();
});

it('nags the owner AND escalates to managers in the same run when a lead is already old enough for both thresholds', function () {
    // Mirrors EscalateUntouchedLeads' own equivalent test -- a gap in the
    // scheduler (or a lead already old before this feature existed)
    // shouldn't make the manager tier wait an extra cycle just because the
    // owner nag happens to fire in this same run.
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(30));

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
    Notification::assertSentTo($manager, NotSureLeadEscalatedNotification::class);
});

it('does not escalate to managers while still short of the total manager threshold, even though the owner was already nagged', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(10));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(4)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($manager, NotSureLeadEscalatedNotification::class);
});

it('re-fires the manager escalation on a later run once the cooldown has passed, but not within it', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(24));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(18)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);
    Notification::assertSentToTimes($manager, NotSureLeadEscalatedNotification::class, 1);

    // Same day, still well within the cooldown -- must not re-fire yet.
    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);
    Notification::assertSentToTimes($manager, NotSureLeadEscalatedNotification::class, 1);

    // Cooldown has elapsed -- still unaddressed, so it fires again.
    $lead->forceFill(['notsure_manager_escalated_at' => now()->subHours(21)])->saveQuietly();
    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);
    Notification::assertSentToTimes($manager, NotSureLeadEscalatedNotification::class, 2);
});

it('stops notifying once the lead gets real staff engagement, even after the owner nag already fired', function () {
    Notification::fake();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(24));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(18)]);

    CallLog::factory()->for($lead, 'callable')->create(['user_id' => $owner->id, 'called_at' => now()->subHour()]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($manager, NotSureLeadEscalatedNotification::class);
});

it('suppresses the manager escalation when SendStagnationAlerts already escalated this same lead to managers today', function () {
    // Construct the real overlap: a Not-Sure lead old enough that BOTH this
    // command's manager tier AND SendStagnationAlerts' own manager tier
    // would otherwise fire for it (SendStagnationAlerts' own default
    // lead-days=7/manager-days=3 means 10+ days of total silence).
    $admin = User::factory()->role(UserRole::Admin)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner, ['created_at' => now()->subDays(11)]);
    backdateNotSure($lead, now()->subHours(24));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(18)]);
    $lead->activities()->update(['created_at' => $lead->created_at]);

    // Real DB write (not faked) -- simulates SendStagnationAlerts having
    // already escalated this exact lead to managers earlier today.
    $admin->notify(new LeadStagnationEscalatedNotification($lead, 10));

    Notification::fake();

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertNotSentTo($admin, NotSureLeadEscalatedNotification::class);
});

it('does escalate normally when no SendStagnationAlerts notification exists for this lead today', function () {
    Notification::fake();
    $admin = User::factory()->role(UserRole::Admin)->create();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = notSureLead($owner);
    backdateNotSure($lead, now()->subHours(24));
    stampNotSureGuards($lead, ['notsure_owner_notified_at' => now()->subHours(18)]);

    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);

    Notification::assertSentTo($admin, NotSureLeadEscalatedNotification::class);
});

it('still sends the original immediate ping, unchanged, the moment goal becomes Not Sure', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => LeadStatus::Contacted, 'goal' => null]);

    $lead->update(['goal' => LeadGoal::NotSure]);

    Notification::assertSentTo(
        $owner,
        LeadWantsExpertAdviceNotification::class,
        fn ($n) => $n->lead->is($lead) && $n->isReminder === false,
    );
    expect($lead->fresh()->notsure_at)->not->toBeNull();

    // Immediately running the escalation command must not double-fire the
    // owner nag -- the threshold hasn't elapsed yet.
    $this->artisan('app:escalate-notsure-leads', ['--owner-hours' => 6, '--manager-hours' => 18]);
    Notification::assertNotSentTo($owner, LeadWantsExpertAdviceNotification::class, fn ($n) => $n->isReminder === true);
});
