<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-scrape the configured target on a schedule so the frontend, which polls
// every 30s, always has fresh data. Enable by running `php artisan schedule:work`
// (docker-compose starts this automatically).
Schedule::command('scrape:product')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();
