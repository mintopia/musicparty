<?php

namespace App\Domain\Stats\Jobs;

use App\Domain\Party\Models\Party;
use App\Domain\Stats\Actions\RefreshPartyStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RefreshPartyStatsJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const DELAY_SECONDS = 5;

    public int $uniqueFor = 30;

    public function __construct(public int $partyId)
    {
        $this->onQueue('default');
        $this->delay(self::DELAY_SECONDS);
    }

    public function uniqueId(): string
    {
        return (string) $this->partyId;
    }

    public function handle(RefreshPartyStats $refresh): void
    {
        $party = Party::query()->find($this->partyId);

        if ($party === null) {
            return;
        }

        $refresh($party);
    }
}
