<?php

namespace Tests\Support;

use App\Domain\Playback\PlayerFactory;
use App\Domain\Playback\Testing\FakePlayer;
use Illuminate\Support\ServiceProvider;

class TestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        config(['musicparty.players' => ['fake' => ['label' => 'Fake player', 'class' => FakePlayer::class], ...config('musicparty.players', [])]]);

        $this->app->afterResolving(PlayerFactory::class, fn (PlayerFactory $factory) => $factory->extend('fake', fn (): FakePlayer => new FakePlayer));
    }
}
