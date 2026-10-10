<?php

namespace App\Console;

use App\Domain\Admin\Jobs\SweepExpiredActAsHostSessions;
use App\Domain\Mod\Jobs\RunModScheduledActions;
use App\Domain\Playback\Jobs\CheckSoloistHealth;
use App\Domain\Playback\Jobs\TickPlayback;
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
        $schedule->job(new SweepExpiredActAsHostSessions)->everyMinute()->onOneServer();
        $schedule->job(new TickPlayback)->everyFiveSeconds()->withoutOverlapping()->onOneServer();
        $schedule->job(new CheckSoloistHealth)->everyTenSeconds()->onOneServer();
        $schedule->job(new RunModScheduledActions)->everyFiveSeconds()->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
