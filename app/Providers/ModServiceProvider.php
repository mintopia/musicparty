<?php

namespace App\Providers;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Mod\ModEventDispatcher;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\PartyMods;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ModServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModRegistry::class);
        $this->app->scoped(PartyMods::class);
    }

    public function boot(): void
    {
        Event::listen(PartyScopedEvent::class, fn (PartyScopedEvent $event) => $this->app->make(ModEventDispatcher::class)->handle($event));

        $registry = $this->app->make(ModRegistry::class);

        /** @var list<class-string<Mod>> $mods */
        $mods = config('mods.registered', []);

        foreach ($mods as $class) {
            $registry->register($this->app->make($class));
        }
    }
}
