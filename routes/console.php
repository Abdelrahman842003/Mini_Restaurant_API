<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the daily meal availability reset using closure
Schedule::call(function () {
    DB::table('menu_items')->update([
        'available_quantity' => DB::raw('daily_quantity')
    ]);
})->daily();
