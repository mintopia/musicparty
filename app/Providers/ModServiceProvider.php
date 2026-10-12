<?php

namespace App\Providers;

use App\Domain\Mod\Actions\ResolveDecorations;
use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Mod\Events\ModDisabled;
use App\Domain\Mod\Events\ModEnabled;
use App\Domain\Mod\ModEventDispatcher;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\PartyMods;
use App\Domain\Mod\Whamageddon\Listeners\PlaceWhamageddonRequest;
use App\Domain\Mod\Whamageddon\Listeners\RemoveWhamageddonRequest;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ModServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModRegistry::class);
        $this->app->scoped(PartyMods::class);
        $this->app->scoped(ResolveDecorations::class);
    }

    public function boot(): void
    {
        Event::listen(PartyScopedEvent::class, fn (PartyScopedEvent $event) => $this->app->make(ModEventDispatcher::class)->handle($event));

        Event::listen(ModEnabled::class, [PlaceWhamageddonRequest::class, 'handle']);
        Event::listen(ModDisabled::class, [RemoveWhamageddonRequest::class, 'handle']);

        $registry = $this->app->make(ModRegistry::class);

        /** @var list<class-string<Mod>> $mods */
        $mods = config('mods.registered', []);

        foreach ($mods as $class) {
            $registry->register($this->app->make($class));
        }
    }
}
