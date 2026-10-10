<?php

namespace Tests\Support;

use App\Domain\Playback\PlayerFactory;
use App\Domain\Playback\Testing\FakePlayer;
use App\Support\Realtime\Contracts\RealtimeConnections;
use Illuminate\Support\ServiceProvider;

class TestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RealtimeConnections::class, FakeRealtimeConnections::class);
        config(['musicparty.players' => ['fake' => ['label' => 'Fake player', 'class' => FakePlayer::class], ...config('musicparty.players', [])]]);

        $this->app->afterResolving(PlayerFactory::class, fn (PlayerFactory $factory) => $factory->extend('fake', fn (): FakePlayer => new FakePlayer));
    }
}
