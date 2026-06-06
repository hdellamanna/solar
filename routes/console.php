<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// FASE 2A: generate pending recurring transactions daily at 00:05
Schedule::command('transactions:generate-recurring')
    ->dailyAt('00:05')
    ->name('recurring-tx')
    ->withoutOverlapping();
