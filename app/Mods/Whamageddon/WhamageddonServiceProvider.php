<?php

namespace App\Mods\Whamageddon;

use App\Services\ModSettingsStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class WhamageddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Whamageddon::class, fn (Application $app, array $parameters): Whamageddon => new Whamageddon(
            $parameters['party'],
            $app->make(ModSettingsStore::class),
        ));
    }
}
