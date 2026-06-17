<?php

use App\Enums\CustomerStatus;
use App\Enums\UserRole;
use App\Mail\MonthlyReportReminder;
use App\Models\Customer;
use App\Models\RecurringInvoice;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

it('sends the reminder on the last working day of the month', function () {
    // Find the last non-Sunday of the current month in IST.
    $tz = config('app.display_timezone', 'Asia/Kolkata');
    $lastWorking = Carbon::now($tz)->endOfMonth()->startOfDay();
    while ($lastWorking->dayOfWeek === Carbon::SUNDAY) {
        $lastWorking->subDay();
    }
    Carbon::setTestNow($lastWorking);

    $admin = User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

    $this->artisan('app:send-monthly-report-reminder')->assertSuccessful();

    Mail::assertSent(MonthlyReportReminder::class, 1);
    Mail::assertSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($admin->email));

    Carbon::setTestNow();
});

it('does not send the reminder on any other day', function () {
    // Use the 15th of the month — never the last working day.
    $tz = config('app.display_timezone', 'Asia/Kolkata');
    Carbon::setTestNow(Carbon::now($tz)->startOfMonth()->addDays(14));

    User::factory()->role(UserRole::Admin)->create();
    $customer = Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

    $this->artisan('app:send-monthly-report-reminder')->assertSuccessful();

    Mail::assertNothingSent();

    Carbon::setTestNow();
});

it('skips inactive recurring invoices and inactive customers', function () {
    $tz = config('app.display_timezone', 'Asia/Kolkata');
    $lastWorking = Carbon::now($tz)->endOfMonth()->startOfDay();
    while ($lastWorking->dayOfWeek === Carbon::SUNDAY) {
        $lastWorking->subDay();
    }
    Carbon::setTestNow($lastWorking);

    User::factory()->role(UserRole::Admin)->create();

    // Inactive recurring invoice — should not trigger a reminder.
    $c1 = Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    RecurringInvoice::factory()->create(['customer_id' => $c1->id, 'is_active' => false]);

    // Inactive customer — should not trigger a reminder.
    $c2 = Customer::factory()->create(['status' => CustomerStatus::Inactive->value]);
    RecurringInvoice::factory()->create(['customer_id' => $c2->id, 'is_active' => true]);

    $this->artisan('app:send-monthly-report-reminder')->assertSuccessful();

    Mail::assertNothingSent();

    Carbon::setTestNow();
});

it('sends to admin, manager, and accounts but not sales or support', function () {
    $tz = config('app.display_timezone', 'Asia/Kolkata');
    $lastWorking = Carbon::now($tz)->endOfMonth()->startOfDay();
    while ($lastWorking->dayOfWeek === Carbon::SUNDAY) {
        $lastWorking->subDay();
    }
    Carbon::setTestNow($lastWorking);

    $admin = User::factory()->role(UserRole::Admin)->create();
    $manager = User::factory()->role(UserRole::Manager)->create();
    $accounts = User::factory()->role(UserRole::Accounts)->create();
    $sales = User::factory()->role(UserRole::Sales)->create();
    $support = User::factory()->role(UserRole::Support)->create();

    $customer = Customer::factory()->create(['status' => CustomerStatus::Active->value]);
    RecurringInvoice::factory()->create(['customer_id' => $customer->id, 'is_active' => true]);

    $this->artisan('app:send-monthly-report-reminder')->assertSuccessful();

    Mail::assertSent(MonthlyReportReminder::class, 3);
    Mail::assertSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($admin->email));
    Mail::assertSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($manager->email));
    Mail::assertSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($accounts->email));
    Mail::assertNotSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($sales->email));
    Mail::assertNotSent(MonthlyReportReminder::class, fn ($m) => $m->hasTo($support->email));

    Carbon::setTestNow();
});
