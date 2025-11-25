<?php

namespace App\Mods\Whamageddon\Listeners;

use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Mods\Whamageddon\Whamageddon;
use Illuminate\Contracts\Queue\ShouldQueue;

class ModSettingDeletedListener implements ShouldQueue
{
    public function handle(PartyModSettingDeletedEvent $event): void
    {
        $this->handleEnabled($event->party, $event->setting);
    }

    protected function handleEnabled(Party $party, ModSetting $setting): void
    {
        if ($setting->mod->code !== 'whamageddon') {
            return;
        }
        if ($setting->code !== 'enabled') {
            return;
        }

        $whamageddon = new Whamageddon($party);
        $whamageddon->disable();
    }
}
