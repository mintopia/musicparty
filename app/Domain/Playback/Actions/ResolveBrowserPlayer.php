<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\Players\BrowserPlayer;
use App\Models\Party;

readonly class ResolveBrowserPlayer
{
    public function __construct(private PartyPlayers $players) {}

    /**
     * @throws PlayerDisconnectedException
     */
    public function __invoke(Party $party): BrowserPlayer
    {
        $player = $this->players->for($party);

        if (! $player instanceof BrowserPlayer) {
            throw new PlayerDisconnectedException('This Party is not using the Browser Player.');
        }

        return $player;
    }
}
