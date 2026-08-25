<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:expire-grants')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('billing:reconcile-stripe')->hourly()->withoutOverlapping();
Schedule::command('reports:deliver-scheduled')->everyTenMinutes()->withoutOverlapping();
Schedule::command('reminders:send')->hourly()->withoutOverlapping();
Schedule::command('webhooks:deliver')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('billing:reconcile-seats')->hourly()->withoutOverlapping();
