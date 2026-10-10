<?php

namespace App\Domain\Mod\Jobs;

use App\Domain\Mod\Actions\RunScheduledActions;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class RunModScheduledActions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(RunScheduledActions $run): void
    {
        Party::query()->where('state', PartyState::Live)->each(function (Party $party) use ($run): void {
            try {
                $run($party);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
