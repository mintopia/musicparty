<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Players\BrowserPlayer;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Players\SoloistPlayer;

return [
    'site_disk' => env('MUSICPARTY_SITE_DISK', 'public'),
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
    'playback' => [
        'enqueue_backoff' => [5, 15, 30, 60, 120, 300],
    ],
    'public_routes_per_minute' => (int) env('MUSICPARTY_PUBLIC_ROUTES_PER_MINUTE', 60),
    'search_rate_limit' => [
        'burst' => (int) env('MUSICPARTY_SEARCH_BURST', 30),
        'per_second' => (float) env('MUSICPARTY_SEARCH_PER_SECOND', 1),
    ],
    'soloist' => [
        'stale_after_seconds' => (int) env('MUSICPARTY_SOLOIST_STALE_AFTER', 30),
        'disconnect_after_seconds' => (int) env('MUSICPARTY_SOLOIST_DISCONNECT_AFTER', 90),
    ],
    'player_frames' => [
        'max_bytes' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_BYTES', 8192),
        'max_per_minute' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_PER_MINUTE', 120),
    ],
    'players' => [
        'polling' => ['label' => 'Polling player', 'class' => PollingPlayer::class],
        'browser' => ['label' => 'Browser player', 'class' => BrowserPlayer::class],
        'soloist' => ['label' => 'Soloist player', 'class' => SoloistPlayer::class],
    ],
];
