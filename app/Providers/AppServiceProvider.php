<?php

namespace App\Providers;

use App\Domain\Music\Contracts\MusicProvider;
use App\Domain\Music\Providers\SpotifyMusicProvider;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\SystemRandomizer;
use App\Support\RateLimiting\Bucket;
use App\Support\RateLimiting\LeakyBucket;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MusicProvider::class, SpotifyMusicProvider::class);
        $this->app->scoped(PartyPlayers::class);
        $this->app->bind(Bucket::class, LeakyBucket::class);
        $this->app->bind(Randomizer::class, SystemRandomizer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Pulse::user(fn ($user) => [
            'name' => $user->nickname,
            'extra' => $user->getEmail() ?? '',
            'avatar' => $user->avatarUrl() ?? '',
        ]);
    }
}
