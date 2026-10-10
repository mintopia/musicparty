<?php

namespace App\Domain\Mod\Jobs;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class RunModScheduledActions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(EnabledMods $mods): void
    {
        Party::query()
            ->where('state', PartyState::Live)
            ->each(function (Party $party) use ($mods): void {
                try {
                    if ($this->hasScheduledActions($mods, $party)) {
                        RunPartyScheduledActions::dispatch($party->code);
                    }
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
    }

    private function hasScheduledActions(EnabledMods $mods, Party $party): bool
    {
        return array_any($mods->for($party), fn (EnabledMod $enabled): bool => $enabled->mod->scheduledActions() !== []);
    }
}
