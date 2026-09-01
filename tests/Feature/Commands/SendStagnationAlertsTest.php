<?php

use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Mail\StagnationAlert;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\LeadStagnationEscalatedNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * Backdates a lead's created_at and gives it exactly one note at a chosen
 * offset -- created_at is forced far in the past throughout so only the
 * note's age (not the lead's own age) determines whether it reads as
 * stagnant, matching how SendStagnationAlerts' own query is actually
 * structured (created_at < cutoff is the coarse gate, "no note/call/
 * activity newer than cutoff" is what actually decides staleness).
 */
function backdatedLeadWithNote(User $owner, int $noteDaysAgo, string $status = 'new'): Lead
{
    $lead = Lead::factory()->ownedBy($owner->id)->create(['status' => $status]);
    $lead->forceFill(['created_at' => now()->subDays(30)])->save();
    // LogsActivity stamped a "created" activity at the real current moment,
    // before the backdate above -- left alone, that recent activity would
    // make whereDoesntHave('activities', newer than cutoff) see a false
    // "recent touch" and exclude the lead from every stagnant query below.
    $lead->activities()->update(['created_at' => now()->subDays(30)]);

    $note = $lead->notes()->create(['user_id' => $owner->id, 'body' => 'Called and left a voicemail.']);
    $note->forceFill(['created_at' => now()->subDays($noteDaysAgo)])->save();

    return $lead;
}

it('is a no-op on Sunday', function () {
    Mail::fake();
    Notification::fake();
    // Noon IST, safely clear of the UTC/IST day-boundary gap the command's
    // own isSunday() check is timezone-sensitive to (app.display_timezone).
    $this->travelTo(now(config('app.display_timezone'))->next(Carbon::SUNDAY)->setTime(12, 0));

    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedLeadWithNote($owner, 8);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Mail::assertNothingSent();
    Notification::assertNotSentTo($owner, LeadStagnationEscalatedNotification::class);
});

it('emails the owner once a lead passes the lead-days stagnation threshold', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    // Default --lead-days=7: a note 8 days ago is stale, one 6 days ago is not.
    $stale = backdatedLeadWithNote($owner, 8);
    backdatedLeadWithNote($owner, 6);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Mail::assertSent(StagnationAlert::class, function (StagnationAlert $mail) use ($owner, $stale) {
        return $mail->hasTo($owner->email)
            && $mail->leads->pluck('id')->contains($stale->id)
            && $mail->leads->count() === 1;
    });
});

it('does not email the owner about a lead marked Lost, however stale its last note', function () {
    Mail::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    backdatedLeadWithNote($owner, 30, LeadStatus::Lost->value);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Mail::assertNothingSent();
});

it('escalates to every active Admin/Manager once a lead passes the manager threshold', function () {
    Mail::fake();
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $inactiveAdmin = User::factory()->role(UserRole::Admin)->create(['is_active' => false]);
    // Default --lead-days=7 + --manager-days=3 = 10 days.
    $stalled = backdatedLeadWithNote($owner, 11);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Notification::assertSentTo($admin, LeadStagnationEscalatedNotification::class, fn ($n) => $n->lead->is($stalled) && $n->days === 10);
    Notification::assertSentTo($manager, LeadStagnationEscalatedNotification::class);
    Notification::assertNotSentTo($inactiveAdmin, LeadStagnationEscalatedNotification::class);
});

it('does not escalate to managers while a lead is only past the owner threshold', function () {
    Mail::fake();
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    // Past the 7-day owner cutoff, but not yet the 10-day manager cutoff.
    backdatedLeadWithNote($owner, 8);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Notification::assertNotSentTo($manager, LeadStagnationEscalatedNotification::class);
});

it('does not escalate a lead marked Lost to managers, however stale', function () {
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    backdatedLeadWithNote($owner, 30, LeadStatus::Lost->value);

    $this->artisan('app:send-stagnation-alerts')->assertSuccessful();

    Notification::assertNotSentTo($manager, LeadStagnationEscalatedNotification::class);
});

it('respects custom --lead-days/--manager-days thresholds', function () {
    Mail::fake();
    Notification::fake();
    $owner = User::factory()->role(UserRole::Sales)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $lead = backdatedLeadWithNote($owner, 6);

    $this->artisan('app:send-stagnation-alerts', ['--lead-days' => 5, '--manager-days' => 1])->assertSuccessful();

    Mail::assertSent(StagnationAlert::class);
    Notification::assertSentTo($manager, LeadStagnationEscalatedNotification::class, fn ($n) => $n->lead->is($lead) && $n->days === 6);
});
