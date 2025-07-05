<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

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
