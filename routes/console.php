<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the rolling 24-month horizon of managed (recurring, and later tax)
// transactions current. Requires the scheduler to be running: the `ploi` crontab
// on the VM calls `php artisan schedule:run` every minute.
Schedule::command('managed:sync')->dailyAt('02:00');
