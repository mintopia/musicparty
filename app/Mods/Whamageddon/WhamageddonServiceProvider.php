<?php

namespace App\Mods\Whamageddon;

use App\Models\Party;
use App\Services\ModSettingsStore;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class WhamageddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Whamageddon::class, function (Application $app, array $parameters): Whamageddon {
            $party = $parameters['party'] ?? null;
            if (! $party instanceof Party) {
                throw new InvalidArgumentException('Whamageddon requires a "party" parameter.');
            }

            return new Whamageddon($party, $app->make(ModSettingsStore::class));
        });
    }
}
