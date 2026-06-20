<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Morning digest — personalised day-ahead email to every active user at 09:00 IST.
Schedule::command('app:send-morning-digest')
    ->dailyAt('09:00')
    ->timezone('Asia/Kolkata');

// Daily follow-up reminder emails at 09:00 India time.
Schedule::command('app:send-followup-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Kolkata');

// Stagnation alerts at 10:00 IST — leads untouched 7d, deals untouched 10d.
Schedule::command('app:send-stagnation-alerts')
    ->dailyAt('10:00')
    ->timezone('Asia/Kolkata');

// Billing schedule (India time): generate recurring invoices, flag overdue,
// then send payment reminders. Recurring invoice reminders go out at 09:00 IST
// on days 7, 5, 3, and 1 before the next billing date (alternate days in that window).
Schedule::command('app:generate-recurring-invoices')->dailyAt('06:00')->timezone('Asia/Kolkata');
Schedule::command('app:mark-overdue-invoices')->dailyAt('07:00')->timezone('Asia/Kolkata');
Schedule::command('app:send-payment-reminders')->dailyAt('07:30')->timezone('Asia/Kolkata');
Schedule::command('app:send-recurring-invoice-reminders')->dailyAt('09:00')->timezone('Asia/Kolkata');

// Call follow-up reminders — check every 5 minutes so notifications fire close to the scheduled time.
Schedule::command('app:send-call-followup-reminders')->everyFiveMinutes();

// Ticket SLA breach escalation — check hourly during the working day.
Schedule::command('app:check-ticket-sla')->hourly();

// Remind staff to submit their daily report at 6pm India time.
Schedule::command('app:send-daily-report-reminders')->dailyAt('18:00')->timezone('Asia/Kolkata');

// Nightly database backup at 02:00 India time (quiet hours).
Schedule::command('app:backup-database')->dailyAt('02:00')->timezone('Asia/Kolkata');

// Monthly report reminder — fires daily at 09:00 IST but the command exits early
// unless today is the last non-Sunday of the month (the last working day).
Schedule::command('app:send-monthly-report-reminder')->dailyAt('09:00')->timezone('Asia/Kolkata');
