<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\EnqueueBackoff;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;

readonly class ClaimBrowserPlayer
{
    public function __construct(private ResolveBrowserPlayer $resolve, private EnqueueBackoff $backoff) {}

    /**
     * @throws PlayerDisconnectedException
     */
    public function __invoke(Party $party, string $tabId): bool
    {
        $claimed = ($this->resolve)($party)->claim($tabId);

        if ($claimed) {
            $this->backoff->clearForParty($party);
        }

        return $claimed;
    }
}
