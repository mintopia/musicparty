<?php

use App\Events\Cron\DayTickEvent;
use App\Events\Cron\HourTickEvent;
use App\Events\Cron\MinuteTickEvent;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('party:fallback')->everyMinute()->onOneServer();
Schedule::command('party:force')->everyMinute()->onOneServer();
Schedule::command('party:refreshaccesstokens')->everyMinute()->onOneServer();
Schedule::call(function () {
    MinuteTickEvent::dispatch();
})->name('tick:minute')->everyMinute()->onOneServer();
Schedule::call(function () {
    HourTickEvent::dispatch();
})->name('tick:hour')->hourly()->onOneServer();
Schedule::call(function () {
    DayTickEvent::dispatch();
})->name('tick:day')->daily()->onOneServer();
