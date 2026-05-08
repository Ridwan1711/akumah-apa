<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('invoices:generate-monthly')->monthlyOn(1, '06:00');
Schedule::command('invoices:check-overdue')->dailyAt('07:00');
Schedule::command('schedule:remind-tomorrow')
    ->dailyAt('19:00')
    ->timezone('Asia/Jakarta')
    ->onOneServer();
Schedule::command('notifications:send-reminders')
    ->dailyAt('08:00')
    ->timezone('Asia/Jakarta')
    ->onOneServer();
Schedule::command('location:prune-teacher-logs')
    ->dailyAt('02:00')
    ->timezone('Asia/Jakarta')
    ->onOneServer();
Schedule::command('teacher-presence:process-timeouts')
    ->everyTenMinutes()
    ->timezone('Asia/Jakarta')
    ->onOneServer();
