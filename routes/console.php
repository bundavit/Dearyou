<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('dearyou:process-storage')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('dearyou:prune-security-codes')->hourly()->withoutOverlapping();
