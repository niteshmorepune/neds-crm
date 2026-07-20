<?php

use App\Enums\RecurringFrequency;
use App\Enums\UserRole;
use App\Mail\SubscriptionRenewalReminder;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionRenewalDueSoon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Mail::fake();
    Notification::fake();
});

it('emails and bell-notifies every active admin when a subscription is due to renew soon', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $subscription = Subscription::factory()->create([
        'name' => 'Claude Subscription',
        'renewal_date' => now()->addDays(3),
        'reminder_days_before' => 7,
    ]);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();

    Notification::assertSentTo($admin, SubscriptionRenewalDueSoon::class);
    Notification::assertNotSentTo($manager, SubscriptionRenewalDueSoon::class);
    Mail::assertSent(SubscriptionRenewalReminder::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->subscription->is($subscription));
});

it('does not remind twice for the same renewal_date', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->addDays(3),
        'reminder_days_before' => 7,
    ]);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($admin, SubscriptionRenewalDueSoon::class, 1);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();
    Notification::assertSentToTimes($admin, SubscriptionRenewalDueSoon::class, 1);
});

it('does not remind for a subscription outside its reminder window', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Subscription::factory()->create([
        'renewal_date' => now()->addDays(30),
        'reminder_days_before' => 7,
    ]);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();

    Notification::assertNothingSentTo($admin);
});

it('does not remind for an inactive subscription', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    Subscription::factory()->create([
        'renewal_date' => now()->addDays(3),
        'reminder_days_before' => 7,
        'is_active' => false,
    ]);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();

    Notification::assertNothingSentTo($admin);
});

it('rolls the renewal date forward and reminds again next cycle once the old date has passed', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $subscription = Subscription::factory()->create([
        'billing_cycle' => RecurringFrequency::Monthly->value,
        'renewal_date' => now()->subDay(), // already passed -> auto-renewed
        'reminder_days_before' => 7,
        'reminder_sent_for' => now()->subDay(),
    ]);

    $this->artisan('app:send-subscription-renewal-reminders')->assertSuccessful();

    $subscription->refresh();
    expect($subscription->renewal_date->copy()->startOfDay()->gte(now()->startOfDay()))->toBeTrue();
});
