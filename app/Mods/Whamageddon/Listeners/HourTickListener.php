<?php

namespace App\Mods\Whamageddon\Listeners;

use App\Events\Cron\HourTickEvent;
use App\Models\Party;
use App\Mods\Whamageddon\Whamageddon;
use App\Mods\Whamageddon\WhamageddonMod;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class HourTickListener implements ShouldQueue
{
    public function handle(HourTickEvent $event): void
    {
        $parties = Party::whereActive(true)->whereHas('mods', function ($query) {
            $query->where('mods.code', WhamageddonMod::CODE)->whereNull('mods.removed_at');
        })->get();

        foreach ($parties as $party) {
            try {
                Log::info("{$party} Whamageddon: Processing hourly tick");
                $whamageddon = new Whamageddon($party);
                $whamageddon->hourly();
            } catch (Throwable $e) {
                Log::error("{$party->id} Whamageddon: Hourly tick failed", ['exception' => $e]);
            }
        }
    }
}
