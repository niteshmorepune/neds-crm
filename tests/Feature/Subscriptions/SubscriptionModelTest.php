<?php

use App\Enums\RecurringFrequency;
use App\Models\Subscription;

it('is due for a reminder when renewal_date falls within reminder_days_before and has not been sent yet', function () {
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->addDays(3),
        'reminder_days_before' => 7,
        'reminder_sent_for' => null,
    ]);

    expect($subscription->isDueForReminder())->toBeTrue();
});

it('is not due for a reminder when renewal_date is further out than reminder_days_before', function () {
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->addDays(20),
        'reminder_days_before' => 7,
    ]);

    expect($subscription->isDueForReminder())->toBeFalse();
});

it('is not due for a reminder when already reminded for this exact renewal_date', function () {
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->addDays(3)->startOfDay(),
        'reminder_days_before' => 7,
        'reminder_sent_for' => now()->addDays(3)->startOfDay(),
    ]);

    expect($subscription->isDueForReminder())->toBeFalse();
});

it('is not due for a reminder when inactive', function () {
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->addDays(3),
        'reminder_days_before' => 7,
        'is_active' => false,
    ]);

    expect($subscription->isDueForReminder())->toBeFalse();
});

it('is due exactly on the renewal date itself', function () {
    $subscription = Subscription::factory()->create([
        'renewal_date' => now()->startOfDay(),
        'reminder_days_before' => 7,
    ]);

    expect($subscription->isDueForReminder())->toBeTrue();
});

it('rolls a past-due monthly renewal_date forward to the next upcoming cycle and clears the sent-for guard', function () {
    $subscription = Subscription::factory()->create([
        'billing_cycle' => RecurringFrequency::Monthly->value,
        'renewal_date' => now()->subDays(5),
        'reminder_sent_for' => now()->subDays(5),
    ]);

    $subscription->rollToNextCycleIfPast();
    $subscription->refresh();

    expect($subscription->renewal_date->copy()->startOfDay()->gte(now()->startOfDay()))->toBeTrue()
        ->and($subscription->reminder_sent_for)->toBeNull();
});

it('rolls forward multiple missed cycles if the command has not run for a while', function () {
    $subscription = Subscription::factory()->create([
        'billing_cycle' => RecurringFrequency::Monthly->value,
        'renewal_date' => now()->subMonths(3)->subDays(2),
    ]);

    $subscription->rollToNextCycleIfPast();
    $subscription->refresh();

    expect($subscription->renewal_date->copy()->startOfDay()->gte(now()->startOfDay()))->toBeTrue();
});

it('does not touch an upcoming renewal_date', function () {
    $renewalDate = now()->addDays(10)->startOfDay();
    $subscription = Subscription::factory()->create(['renewal_date' => $renewalDate]);

    $subscription->rollToNextCycleIfPast();
    $subscription->refresh();

    expect($subscription->renewal_date->equalTo($renewalDate))->toBeTrue();
});
