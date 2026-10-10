<?php

namespace App\Domain\Admin\Jobs;

use App\Domain\Admin\Actions\EndExpiredActAsHostSessions;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;

class SweepExpiredActAsHostSessions implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function handle(EndExpiredActAsHostSessions $endExpired): void
    {
        $endExpired->handle();
    }
}
