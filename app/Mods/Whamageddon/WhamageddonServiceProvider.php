<?php

namespace App\Mods\Whamageddon;

use App\Events\Cron\HourTickEvent;
use App\Mods\Whamageddon\Listeners\HourTickListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WhamageddonServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(HourTickEvent::class, HourTickListener::class);
    }
}
