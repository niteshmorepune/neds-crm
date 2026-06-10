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
