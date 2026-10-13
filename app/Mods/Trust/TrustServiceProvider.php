<?php

namespace App\Mods\Trust;

use App\Mods\Trust\Console\CalculateTrustCommand;
use Illuminate\Support\ServiceProvider;

class TrustServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->commands([CalculateTrustCommand::class]);
    }
}
