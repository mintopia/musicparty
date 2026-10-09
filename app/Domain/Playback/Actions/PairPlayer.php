<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;

class PairPlayer
{
    /**
     * @throws IncompatibleProviderException
     */
    public function __invoke(Player $player, MusicProvider $provider): void
    {
        if (! in_array($provider->id(), $player->compatibleProviders(), true)) {
            throw IncompatibleProviderException::for($player->kind(), $provider->id(), $player->compatibleProviders());
        }
    }
}
