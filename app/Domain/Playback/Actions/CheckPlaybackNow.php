<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Jobs\PollPlayback;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Players\PollingPlayer;

readonly class CheckPlaybackNow
{
    public function __construct(private PartyPlayers $players) {}

    public function __invoke(Party $party): bool
    {
        if ($party->state !== PartyState::Live || ! $this->players->for($party) instanceof PollingPlayer) {
            return false;
        }

        PollPlayback::checkNow($party->code);

        return true;
    }
}
