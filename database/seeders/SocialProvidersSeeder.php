<?php

namespace Database\Seeders;

use App\Domain\Identity\SocialProviders\DiscordProvider;
use App\Domain\Identity\SocialProviders\LaravelPassportProvider;
use App\Domain\Identity\SocialProviders\SpotifyProvider;
use App\Domain\Identity\SocialProviders\SteamProvider;
use App\Domain\Identity\SocialProviders\TwitchProvider;
use Illuminate\Database\Seeder;

class SocialProvidersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = [
            DiscordProvider::class,
            SteamProvider::class,
            TwitchProvider::class,
            LaravelPassportProvider::class,
            SpotifyProvider::class,
        ];
        foreach ($classes as $className) {
            $provider = new $className;
            $provider->install();
            $provider->installSettings();
        }
    }
}
