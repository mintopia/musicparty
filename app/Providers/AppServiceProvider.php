<?php

namespace App\Providers;

use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\PlaybackClient;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlayerFactory;
use App\Domain\Playback\Spotify\SpotifyPlaybackClient;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\SystemRandomizer;
use App\Support\RateLimiting\Bucket;
use App\Support\RateLimiting\LeakyBucket;
use Fruitcake\LaravelDebugbar\ServiceProvider as DebugbarServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PlaybackClient::class, SpotifyPlaybackClient::class);
        $this->app->scoped(PartyPlayers::class);
        $this->app->singleton(PlayerFactory::class);
        $this->app->bind(Bucket::class, LeakyBucket::class);
        $this->app->bind(Randomizer::class, SystemRandomizer::class);

        if ($this->app->environment('local') && config('app.debug') && class_exists(DebugbarServiceProvider::class)) {
            $this->app->register(DebugbarServiceProvider::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Relation::morphMap([
            'App\\Models\\User' => User::class,
            'App\\Models\\Party' => Party::class,
            'App\\Models\\SocialProvider' => SocialProvider::class,
        ]);

        Pulse::user(fn ($user) => [
            'name' => $user->nickname,
            'extra' => $user->getEmail() ?? '',
            'avatar' => $user->avatarUrl() ?? '',
        ]);
    }
}
