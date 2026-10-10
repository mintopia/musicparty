<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Testing\FakePlayer;

return [
    'allow_overlapping_updates' => env('MUSICPARTY_ALLOW_OVERLAPPING_UPDATES', true),
    'webhook_dispatch_after_request' => env('MUSICPARTY_WEBHOOK_DISPATCH_AFTER_REQUEST', true),
    'webhook_should_queue' => env('MUSICPARTY_WEBHOOK_SHOULD_QUEUE', true),
    'fallback_minimum_queue' => (int) env('MUSICPARTY_FALLBACK_MINIMUM_QUEUE', 5),
    'just_in_time_lead_seconds' => (int) env('MUSICPARTY_JIT_LEAD_SECONDS', 15),
    'music_providers' => [
        'fake' => ['label' => 'Fake (development)', 'class' => FakeMusicProvider::class],
    ],
    'polling' => [
        'min_delay_seconds' => (int) env('MUSICPARTY_POLL_MIN_DELAY', 2),
        'normal_delay_seconds' => (int) env('MUSICPARTY_POLL_NORMAL_DELAY', 10),
        'idle_delay_seconds' => (int) env('MUSICPARTY_POLL_IDLE_DELAY', 60),
        'backoff_cap_seconds' => (int) env('MUSICPARTY_POLL_BACKOFF_CAP', 300),
    ],
    'player_frames' => [
        'max_bytes' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_BYTES', 8192),
        'max_per_minute' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_PER_MINUTE', 120),
    ],
    'players' => [
        'fake' => ['label' => 'Fake player', 'class' => FakePlayer::class],
        'polling' => ['label' => 'Polling player', 'class' => PollingPlayer::class],
    ],
];
