<?php

namespace App\Jobs;

use App\Events\Party\PlayerCommand;
use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RequestEncoreState implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public function __construct(public string $code)
    {
        $this->onQueue('partyupdates');
    }

    public function handle(): void
    {
        $party = Party::where('code', $this->code)->first();
        if ($party === null) {
            return;
        }

        PlayerCommand::dispatch($party, PlaybackSource::Encore, ['type' => 'get_state']);
    }
}
