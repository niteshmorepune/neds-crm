<?php

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Notifications\ContractRenewalDueSoon;
use Illuminate\Support\Facades\Notification;

it('notifies accounts/admin/manager and the owning sales rep for a contract ending within 30 days', function () {
    Notification::fake();

    $rep = User::factory()->role(UserRole::Sales)->create();
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $customer = Customer::factory()->create(['owner_id' => $rep->id]);
    $template = RecurringInvoice::factory()->create([
        'customer_id' => $customer->id,
        'end_date' => now()->addDays(15),
    ]);

    $this->artisan('app:send-contract-renewal-reminders')->assertSuccessful();

    Notification::assertSentTo($rep, ContractRenewalDueSoon::class);
    Notification::assertSentTo($accounts, ContractRenewalDueSoon::class);
    $template->refresh();
    expect($template->renewal_reminder_sent_for->toDateString())->toBe($template->end_date->toDateString());
});

it('does not notify twice for the same end_date', function () {
    Notification::fake();

    $template = RecurringInvoice::factory()->create([
        'end_date' => now()->addDays(15),
        'renewal_reminder_sent_for' => now()->addDays(15)->toDateString(),
    ]);

    $this->artisan('app:send-contract-renewal-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('sends again once a contract is renewed to a later end_date', function () {
    Notification::fake();

    $template = RecurringInvoice::factory()->create([
        'end_date' => now()->addDays(15),
        'renewal_reminder_sent_for' => now()->addDays(2)->toDateString(), // reminded for an earlier, now-superseded end_date
    ]);

    $this->artisan('app:send-contract-renewal-reminders')->assertSuccessful();

    $recipients = User::where('is_active', true)->withAnyRole(UserRole::Accounts, UserRole::Admin, UserRole::Manager)->get();
    foreach ($recipients as $user) {
        Notification::assertSentTo($user, ContractRenewalDueSoon::class);
    }
    $template->refresh();
    expect($template->renewal_reminder_sent_for->toDateString())->toBe($template->end_date->toDateString());
});

it('excludes a contract ending more than 30 days out or already ended', function () {
    Notification::fake();

    RecurringInvoice::factory()->create(['end_date' => now()->addDays(45)]);
    RecurringInvoice::factory()->create(['end_date' => now()->subDays(1)]);
    RecurringInvoice::factory()->create(['end_date' => null]);

    $this->artisan('app:send-contract-renewal-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});

it('excludes an inactive template even if its end_date is within the window', function () {
    Notification::fake();

    RecurringInvoice::factory()->create(['is_active' => false, 'end_date' => now()->addDays(10)]);

    $this->artisan('app:send-contract-renewal-reminders')->assertSuccessful();

    Notification::assertNothingSent();
});
