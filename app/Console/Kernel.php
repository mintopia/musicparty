<?php

namespace App\Console;

use App\Events\Cron\DayTickEvent;
use App\Events\Cron\HourTickEvent;
use App\Events\Cron\MinuteTickEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Event;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('sanctum:prune-expired --hours=24')->daily()->onOneServer();
        $schedule->command('telescope:prune')->daily()->onOneServer();
        $schedule->command('party:fallback')->everyMinute()->onOneServer();
        $schedule->command('party:force')->everyMinute()->onOneServer();
        $schedule->command('party:refreshaccesstokens')->everyMinute()->onOneServer();
        $schedule->call(function () {
            MinuteTickEvent::dispatch();
        })->name('tick:minute')->everyMinute()->onOneServer();
        $schedule->call(function () {
            HourTickEvent::dispatch();
        })->name('tick:hour')->hourly()->onOneServer();
        $schedule->call(function () {
            DayTickEvent::dispatch();
        })->name('tick:day')->daily()->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
