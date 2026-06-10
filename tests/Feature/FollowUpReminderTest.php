<?php

use App\Enums\DealStage;
use App\Enums\UserRole;
use App\Mail\FollowUpReminder;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

it('emails owners their due, open follow-ups only', function () {
    Mail::fake();

    $owner = User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->ownedBy($owner->id)->dueFollowUp()->create();
    Deal::factory()->ownedBy($owner->id)->stage(DealStage::New)->create(['next_follow_up_at' => now()->subDay()]);

    // This owner's only due item is a WON deal — should be excluded.
    $wonOnly = User::factory()->role(UserRole::Sales)->create();
    Deal::factory()->ownedBy($wonOnly->id)->stage(DealStage::Won)->create(['next_follow_up_at' => now()->subDay()]);

    // No due items at all.
    User::factory()->role(UserRole::Sales)->create();

    $this->artisan('app:send-followup-reminders')->assertSuccessful();

    Mail::assertSent(FollowUpReminder::class, 1);
    Mail::assertSent(FollowUpReminder::class, fn (FollowUpReminder $mail) => $mail->hasTo($owner->email));
    Mail::assertNotSent(FollowUpReminder::class, fn (FollowUpReminder $mail) => $mail->hasTo($wonOnly->email));
});

it('does not email when nothing is due', function () {
    Mail::fake();

    User::factory()->role(UserRole::Sales)->create();
    Lead::factory()->create(['next_follow_up_at' => now()->addWeek()]); // future, not due

    $this->artisan('app:send-followup-reminders')->assertSuccessful();

    Mail::assertNothingSent();
});
