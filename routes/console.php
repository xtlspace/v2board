<?php

use App\Utils\CacheKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->describe('Display an inspiring quote');

Schedule::call(function () {
    Cache::put(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null), time());
})->everyMinute();

Schedule::command('traffic:update')->everyMinute()->withoutOverlapping();
Schedule::command('v2board:statistics')->dailyAt('0:10');
Schedule::command('check:order')->everyMinute()->withoutOverlapping();
Schedule::command('check:commission')->everyFifteenMinutes();
Schedule::command('check:ticket')->everyMinute();
Schedule::command('check:renewal')->dailyAt('22:30');
Schedule::command('reset:traffic')->daily();
Schedule::command('reset:log')->daily();
Schedule::command('send:remindMail')->dailyAt('11:30');
Schedule::command('horizon:snapshot')->everyFiveMinutes();
