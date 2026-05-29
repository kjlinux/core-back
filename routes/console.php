<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('health:check-devices')->everyMinute()->withoutOverlapping();
Schedule::command('firmware:fail-stuck-ota')->everyMinute()->withoutOverlapping();

Schedule::command('support:check-prolonged-offline')->dailyAt('07:30')->withoutOverlapping();

Schedule::command('subscriptions:rollover-prepaid')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('subscriptions:check-expiry')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('subscriptions:send-reminders')->dailyAt('08:00')->withoutOverlapping();

// Rapports planifiés — évalue les échéances toutes les heures.
Schedule::command('reports:dispatch-scheduled')->hourly()->withoutOverlapping();
