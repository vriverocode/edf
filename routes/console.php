<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Artisan::command('inspire', function () {
//     $this->comment(Inspiring::quote());
// })->purpose('Display an inspiring quote')->hourly();
Schedule::command('app:monthly-quota')->everyMinute();
Schedule::command('app:booking-pending-pay-reminders')->everyThirtyMinutes();
Schedule::command('app:auto-complete-bookings')->everyThirtyMinutes();
// Schedule::command('app:active-ordesactive-air-bnb-users')->dailyAt('10:00');
Schedule::command('app:active-ordesactive-air-bnb-users')->everyMinute();
Schedule::command('app:check-user-morosos')->everyMinute();
