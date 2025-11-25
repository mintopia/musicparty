<?php

namespace App\Mods\Whamageddon\Listeners;

use App\Events\Mods\PartyModSettingUpdatedEvent;
use App\Models\PartyModSetting;
use App\Mods\Whamageddon\Whamageddon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ModSettingUpdatedListener implements ShouldQueue
{
    public function handle(PartyModSettingUpdatedEvent $event): void
    {
        if ($event->partyModSetting->setting->mod->code !== 'whamageddon') {
            Log::debug("[Whamageddon] Ignoring event from {$event->partyModSetting}");
            return;
        }
        $this->handleEnabled($event->partyModSetting, $event->changed);
    }

    protected function handleEnabled(PartyModSetting $setting, array $changed): void
    {
        if ($setting->setting->code !== 'enabled') {
            Log::debug("[Whamageddon] Setting is {$setting->setting->code}, ignoring");
            return;
        }

        if (!array_key_exists('value', $changed)) {
            Log::debug("[Whamageddon] Enabled value wasn't changed, ignoring");
            return;
        }

        $whamageddon = new Whamageddon($setting->party);
        if ($setting->value) {
            $whamageddon->enable();
        } else {
            $whamageddon->disable();
        }
    }
}
