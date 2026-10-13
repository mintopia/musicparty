<?php

namespace App\Mods;

use Illuminate\Support\ServiceProvider;

class ModServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $registry = new ModRegistry(app_path('Mods'));
        $registry->discover();
        $this->app->instance(ModRegistry::class, $registry);

        foreach ($registry->providers() as $provider) {
            $this->app->register($provider);
        }
    }
}
