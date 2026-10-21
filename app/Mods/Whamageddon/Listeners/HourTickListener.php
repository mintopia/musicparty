<?php

namespace App\Mods\Whamageddon\Listeners;

use App\Events\Cron\HourTickEvent;
use App\Models\PartyModSetting;
use App\Mods\Whamageddon\Whamageddon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Reverb\Loggers\Log;

class HourTickListener implements ShouldQueue
{
    public function handle(HourTickEvent $event): void
    {
        $settings = PartyModSetting::whereHas('setting', function ($query) {
            $query->where('code', 'enabled');
            $query->whereHas('mod', function ($query) {
                $query->where('code', 'whamageddon');
            });
        })->whereHas('party', function ($query) {
            $query->whereActive(true);
        })->with('party')->get();

        foreach ($settings as $setting) {
            Log::info("{$setting->party} Whamageddon: Processing hourly tick");
            $whamageddon = new Whamageddon($setting->party);
            $whamageddon->hourly();
        }
    }
}
