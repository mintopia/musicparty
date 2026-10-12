<?php

namespace App\Console\Commands;

use App\Domain\Playback\Jobs\TickParty;

class PartyUpdateState extends PartyCommand
{
    protected $signature = 'party:updatestate {code : The Party code}';

    protected $description = 'Run one playback tick for a Party now';

    protected function perform(): int
    {
        $party = $this->resolveParty((string) $this->argument('code'));

        if ($party === null) {
            return self::FAILURE;
        }

        dispatch_sync(new TickParty($party->code));
        $this->info("Ticked Party {$party->code}.");

        return self::SUCCESS;
    }
}
