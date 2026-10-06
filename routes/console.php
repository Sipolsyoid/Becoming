<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('habits:send-reminders')->everyMinute()->withoutOverlapping();
Schedule::command('photos:recover')->everyMinute()->withoutOverlapping();
Schedule::command('photos:cleanup --delete')->daily()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
