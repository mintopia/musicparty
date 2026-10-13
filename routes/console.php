<?php

use App\Enums\ScheduleFrequency;
use App\Mods\ModScheduler;
use Illuminate\Support\Facades\Schedule;

Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
Schedule::command('party:fallback')->everyMinute()->onOneServer();
Schedule::command('party:force')->everyMinute()->onOneServer();
Schedule::command('party:refreshaccesstokens')->everyMinute()->onOneServer();
Schedule::call(fn () => app(ModScheduler::class)->run(ScheduleFrequency::Minute))
    ->name('mods:minute')->everyMinute()->onOneServer();
Schedule::call(fn () => app(ModScheduler::class)->run(ScheduleFrequency::Hour))
    ->name('mods:hour')->hourly()->onOneServer();
Schedule::call(fn () => app(ModScheduler::class)->run(ScheduleFrequency::Day))
    ->name('mods:day')->daily()->onOneServer();
