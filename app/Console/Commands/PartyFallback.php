<?php

namespace App\Console\Commands;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Jobs\TickParty;

class PartyFallback extends PartyCommand
{
    public const int STALE_AFTER_SECONDS = 90;

    protected $signature = 'party:fallback';

    protected $description = 'Tick every Live Party whose last playback tick is over 90 seconds old';

    protected function perform(): int
    {
        $threshold = now()->subSeconds(self::STALE_AFTER_SECONDS);
        $ticked = 0;

        Party::query()
            ->where('state', PartyState::Live)
            ->whereNotNull('player_kind')
            ->each(function (Party $party) use ($threshold, &$ticked): void {
                $last = TickParty::lastTickedAt($party->code);

                if ($last === null || $last->lessThan($threshold)) {
                    TickParty::dispatch($party->code);
                    $this->line("Ticking Party {$party->code}.");
                    $ticked++;
                }
            });

        $this->info("Ticked {$ticked} stale Live Part".($ticked === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
