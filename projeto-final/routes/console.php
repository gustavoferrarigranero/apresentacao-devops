<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('deployment:heartbeat --wait=0')->everyMinute()->withoutOverlapping();
Schedule::command('queue:monitor '.config('queue.default').':default --max=500')->everyMinute();
