<?php
namespace App\Mods\Whamageddon;

use App\Events\Cron\HourTickEvent;
use App\Events\Mods\PartyModSettingDeletedEvent;
use App\Events\Mods\PartyModSettingUpdatedEvent;
use App\Mods\Whamageddon\Commands\InstallCommand;
use App\Mods\Whamageddon\Listeners\HourTickListener;
use App\Mods\Whamageddon\Listeners\ModSettingDeletedListener;
use App\Mods\Whamageddon\Listeners\ModSettingUpdatedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class WhammageddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }
    public function boot()
    {
        $this->registerCommands();
        $this->registerListeners();
    }

    protected function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
        ]);
    }

    protected function registerListeners(): void
    {
        Event::listen(PartyModSettingUpdatedEvent::class, ModSettingUpdatedListener::class);
        Event::listen(PartyModSettingDeletedEvent::class, ModSettingDeletedListener::class);
        Event::listen(HourTickEvent::class, HourTickListener::class);
    }
}
