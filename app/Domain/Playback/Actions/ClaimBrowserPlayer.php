<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Models\Party;

readonly class ClaimBrowserPlayer
{
    public function __construct(private ResolveBrowserPlayer $resolve) {}

    /**
     * @throws PlayerDisconnectedException
     */
    public function __invoke(Party $party, string $tabId): bool
    {
        return ($this->resolve)($party)->claim($tabId);
    }
}
