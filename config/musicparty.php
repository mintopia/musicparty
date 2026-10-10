<?php

use App\Domain\Music\Testing\FakeMusicProvider;
use App\Domain\Playback\Players\BrowserPlayer;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Players\SoloistPlayer;
use App\Domain\Playback\Testing\FakePlayer;

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
    'soloist' => [
        'stale_after_seconds' => (int) env('MUSICPARTY_SOLOIST_STALE_AFTER', 30),
        'disconnect_after_seconds' => (int) env('MUSICPARTY_SOLOIST_DISCONNECT_AFTER', 90),
    ],
    'player_frames' => [
        'max_bytes' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_BYTES', 8192),
        'max_per_minute' => (int) env('MUSICPARTY_PLAYER_FRAME_MAX_PER_MINUTE', 120),
    ],
    'players' => [
        'fake' => ['label' => 'Fake player', 'class' => FakePlayer::class],
        'polling' => ['label' => 'Polling player', 'class' => PollingPlayer::class],
        'browser' => ['label' => 'Browser player', 'class' => BrowserPlayer::class],
        'soloist' => ['label' => 'Soloist player', 'class' => SoloistPlayer::class],
    ],
];
