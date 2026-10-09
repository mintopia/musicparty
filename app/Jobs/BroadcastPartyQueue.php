<?php

namespace App\Jobs;

use App\Domain\Queue\Broadcast\PartyQueueSnapshot;
use App\Events\Party\QueueUpdatedEvent;
use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class BroadcastPartyQueue implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public const COALESCE_SECONDS = 1;

    public int $uniqueFor = 30;

    public function __construct(public string $partyCode)
    {
        $this->onQueue('partyupdates');
        $this->delay(self::COALESCE_SECONDS);
    }

    public function uniqueId(): string
    {
        return $this->partyCode;
    }

    public function handle(PartyQueueSnapshot $snapshots): void
    {
        $party = Party::query()->where('code', $this->partyCode)->first();

        if ($party === null) {
            return;
        }

        QueueUpdatedEvent::dispatch($party->code, $snapshots->build($party));
    }
}
