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

// Weekly owner digest — AI synthesis of pipeline, cash position, and
// at-risk clients for Admin/Manager, Monday 09:00 IST. Skips entirely when
// AI is disabled (see the command's own docblock for why).
Schedule::command('app:send-weekly-owner-digest')
    ->weeklyOn(1, '09:00')
    ->timezone('Asia/Kolkata');

// Leadership digest at 09:15 IST: yesterday's AI-drafted client updates
// (approved vs still pending), any drafts sitting unapproved 2+ days, and
// active projects with no completed task or note in 5+ days. Recomputes live
// state every run (no "already sent" suppression) so an unresolved item
// keeps surfacing until it's actually dealt with.
Schedule::command('app:send-project-updates-digest')
    ->dailyAt('09:15')
    ->timezone('Asia/Kolkata');

// Stagnation alerts at 10:00 IST — leads untouched 7d, deals untouched 10d.
Schedule::command('app:send-stagnation-alerts')
    ->dailyAt('10:00')
    ->timezone('Asia/Kolkata');

// AI-drafts a nurture follow-up (staff-only note) for New leads nobody has
// personally followed up on yet, at day 1 / 3 / 7 since they enquired.
// Idempotent per lead+touch, so a missed run just catches up the next day.
Schedule::command('app:draft-lead-nurture-followups')
    ->dailyAt('10:30')
    ->timezone('Asia/Kolkata');

// Billing schedule (India time): generate recurring invoices, flag overdue,
// then send payment reminders. Recurring invoice reminders go out at 09:00 IST
// on days 7, 5, 3, and 1 before the next billing date (alternate days in that window).
// Due-soon warnings go out at 08:00 IST to notify accounts staff 7 days before due date.
Schedule::command('app:generate-recurring-invoices')->dailyAt('06:00')->timezone('Asia/Kolkata');
Schedule::command('app:mark-overdue-invoices')->dailyAt('07:00')->timezone('Asia/Kolkata');
Schedule::command('app:send-payment-reminders')->dailyAt('07:30')->timezone('Asia/Kolkata');
Schedule::command('app:send-recurring-invoice-due-warnings')->dailyAt('08:00')->timezone('Asia/Kolkata');
Schedule::command('app:send-payment-promise-reminders')->dailyAt('08:15')->timezone('Asia/Kolkata');
Schedule::command('app:send-contract-renewal-reminders')->dailyAt('08:30')->timezone('Asia/Kolkata');
Schedule::command('app:send-recurring-invoice-reminders')->dailyAt('09:00')->timezone('Asia/Kolkata');

// Call follow-up reminders — check every 5 minutes so notifications fire close to the scheduled time.
Schedule::command('app:send-call-followup-reminders')->everyFiveMinutes();

// Ticket SLA breach escalation — check hourly during the working day.
Schedule::command('app:check-ticket-sla')->hourly();

// Remind staff to submit their daily report at 6pm India time.
Schedule::command('app:send-daily-report-reminders')->dailyAt('18:00')->timezone('Asia/Kolkata');

// AI-drafts a client-facing "today's progress" note (pending review) for
// each active project with tasks completed today, at 18:30 IST. The
// project owner/admin/manager approves via ProjectDailyUpdateReview before
// it reaches the client (portal + email) — never sent automatically.
Schedule::command('app:draft-project-daily-updates')->dailyAt('18:30')->timezone('Asia/Kolkata');

// Nightly database backup at 02:00 India time (quiet hours).
Schedule::command('app:backup-database')->dailyAt('02:00')->timezone('Asia/Kolkata');

// Monthly report reminder — fires daily at 09:00 IST but the command exits early
// unless today is the last non-Sunday of the month (the last working day).
Schedule::command('app:send-monthly-report-reminder')->dailyAt('09:00')->timezone('Asia/Kolkata');

// Auto-create SMDost content briefs for active social media and GMB projects
// on the 1st of each month at 07:30 IST (after recurring invoices are generated).
Schedule::command('app:create-monthly-briefs')->monthlyOn(1, '07:30')->timezone('Asia/Kolkata');

// AI-drafts a "monthly wins" note (staff-only) for each active, owned client,
// summarizing the month that just ended, for the account manager to
// personalize and send. Idempotent per client+month.
Schedule::command('app:draft-monthly-wins-notes')->monthlyOn(1, '07:45')->timezone('Asia/Kolkata');

// Recurring maintenance tasks — creates project-linked tasks and fires in-app
// bell notifications to project leads. Runs daily; the command decides internally
// which templates are due today based on frequency (weekly/biweekly/monthly/quarterly).
Schedule::command('app:dispatch-scheduled-tasks')->dailyAt('08:00')->timezone('Asia/Kolkata');

// AI-drafts festival greeting content for active Social Media/GMB projects
// 7 days ahead of each festival. Idempotent (checks for an existing content
// piece per project+festival) so a missed run just catches up the next day.
Schedule::command('app:draft-festival-greetings')->dailyAt('07:15')->timezone('Asia/Kolkata');
