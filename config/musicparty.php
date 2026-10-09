<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Testing\FakePlayer;

return [
    'allow_overlapping_updates' => env('MUSICPARTY_ALLOW_OVERLAPPING_UPDATES', true),
    'webhook_dispatch_after_request' => env('MUSICPARTY_WEBHOOK_DISPATCH_AFTER_REQUEST', true),
    'webhook_should_queue' => env('MUSICPARTY_WEBHOOK_SHOULD_QUEUE', true),
    'music_providers' => [
        'fake' => ['label' => 'Fake (development)', 'class' => FakeMusicProvider::class],
    ],
    'players' => [
        'fake' => ['label' => 'Fake player', 'class' => FakePlayer::class],
    ],
];
