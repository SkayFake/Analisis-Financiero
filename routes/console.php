<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('creditos:actualizar-cartera')->dailyAt('00:15')->withoutOverlapping();

Schedule::command('activos:depreciar')->monthlyOn(1, '00:30')->withoutOverlapping();
