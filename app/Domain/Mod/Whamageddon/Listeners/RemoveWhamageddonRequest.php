<?php

namespace App\Domain\Mod\Whamageddon\Listeners;

use App\Domain\Mod\Events\ModDisabled;
use App\Domain\Mod\Whamageddon\WhamageddonMod;
use App\Domain\Mod\Whamageddon\WhamageddonStateTracker;
use App\Domain\Queue\Actions\RemoveRequestForMod;

readonly class RemoveWhamageddonRequest
{
    public function __construct(private RemoveRequestForMod $removeRequest, private WhamageddonStateTracker $tracker) {}

    public function handle(ModDisabled $event): void
    {
        if ($event->modId !== WhamageddonMod::ID) {
            return;
        }

        $state = $this->tracker->stateFor($event->party);

        if ($state->request_id !== null) {
            ($this->removeRequest)($event->party, $state->request_id, 'mod:'.WhamageddonMod::ID);
        }

        $this->tracker->clear($event->party);
    }
}
