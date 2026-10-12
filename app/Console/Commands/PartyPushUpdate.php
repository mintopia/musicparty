<?php

namespace App\Console\Commands;

use App\Domain\Party\Broadcast\PartyStateChangedEvent;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;

class PartyPushUpdate extends PartyCommand
{
    protected $signature = 'party:pushupdate {code : The Party code}';

    protected $description = "Re-broadcast a Party's state and Queue";

    protected function perform(): int
    {
        $party = $this->resolveParty((string) $this->argument('code'));

        if ($party === null) {
            return self::FAILURE;
        }

        PartyStateChangedEvent::dispatch($party->code, $party->state->value, (bool) $party->show_qrcode);
        BroadcastPartyQueue::dispatch($party->code);
        $this->info("Pushed state and Queue for Party {$party->code}.");

        return self::SUCCESS;
    }
}
