<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Keep the single Fullscript OAuth token fresh. This does not affect Shopify auth.
app(Schedule::class)
    ->command('fullscript:refresh --if-expiring')
    ->everyFiveMinutes()
    ->withoutOverlapping();
