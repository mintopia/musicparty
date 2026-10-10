<?php

namespace App\Providers;

use App\Domain\Playback\PartyPlayers;
use App\Domain\Queue\Randomizer;
use App\Domain\Queue\SystemRandomizer;
use App\Services\PlayedSongAugmentService;
use App\Services\UpcomingSongAugmentService;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Facades\Pulse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UpcomingSongAugmentService::class);
        $this->app->bind(PlayedSongAugmentService::class);
        $this->app->singleton(PartyPlayers::class);
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
