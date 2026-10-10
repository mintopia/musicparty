<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Models\Party;
use App\Models\User;

readonly class ReleaseBrowserPlayer
{
    public function __construct(private ResolveBrowserPlayer $resolve, private RecordPartyLogEntry $record) {}

    /**
     * @throws PlayerDisconnectedException
     */
    public function __invoke(Party $party, string $tabId, ?User $actor = null): bool
    {
        if (! ($this->resolve)($party)->release($tabId)) {
            return false;
        }

        ($this->record)($party, 'player.disconnected', $actor, 'browser');

        return true;
    }
}
