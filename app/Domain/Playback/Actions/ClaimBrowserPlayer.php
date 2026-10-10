<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Playback\EnqueueBackoff;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Models\Party;

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
