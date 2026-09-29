<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:expire-grants')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('billing:reconcile-stripe')->hourly()->withoutOverlapping();
Schedule::command('billing:process-inbox')->everyMinute()->withoutOverlapping(3);
Schedule::command('reports:deliver-scheduled')->everyTenMinutes()->withoutOverlapping();
Schedule::command('reminders:send')->hourly()->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::call(fn () => DB::table('mobile_auth_challenges')->where('expires_at', '<', now())->delete())->daily()->name('mobile:prune-challenges');
Schedule::call(function (): void {
    foreach (['mobile_social_nonces', 'mobile_social_credentials'] as $table) {
        DB::table($table)->where('expires_at', '<', now())->delete();
    }
})->daily()->name('mobile:prune-social-credentials');
Schedule::command('webhooks:deliver')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('billing:reconcile-seats')->hourly()->withoutOverlapping();
Schedule::command('marketing:process-inbox')->everyMinute()->withoutOverlapping(3)->runInBackground();
