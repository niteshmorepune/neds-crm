<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily follow-up reminder emails at 09:00 India time.
Schedule::command('app:send-followup-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Kolkata');

// Billing schedule (India time): generate recurring invoices, flag overdue,
// then send payment reminders.
Schedule::command('app:generate-recurring-invoices')->dailyAt('06:00')->timezone('Asia/Kolkata');
Schedule::command('app:mark-overdue-invoices')->dailyAt('07:00')->timezone('Asia/Kolkata');
Schedule::command('app:send-payment-reminders')->dailyAt('07:30')->timezone('Asia/Kolkata');

// Ticket SLA breach escalation — check hourly during the working day.
Schedule::command('app:check-ticket-sla')->hourly();

// Remind staff to submit their daily report at 6pm India time.
Schedule::command('app:send-daily-report-reminders')->dailyAt('18:00')->timezone('Asia/Kolkata');

// Nightly database backup at 02:00 India time (quiet hours).
Schedule::command('app:backup-database')->dailyAt('02:00')->timezone('Asia/Kolkata');
