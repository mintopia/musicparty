<?php

namespace App\Console\Commands;

use App\Domain\Identity\Actions\SeedSocialProviders as SeedSocialProvidersAction;
use Illuminate\Console\Command;

class SeedSocialProviders extends Command
{
    protected $signature = 'providers:seed';

    protected $description = 'Seed the Discord, Twitch, Steam and Spotify login providers from config, without overwriting stored values';

    public function handle(SeedSocialProvidersAction $seed): int
    {
        foreach ($seed() as $code => $enabled) {
            $this->line($code.': '.($enabled ? 'enabled' : 'disabled'));
        }

        return self::SUCCESS;
    }
}
