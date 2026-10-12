<?php

namespace App\Domain\Music\Providers\Spotify;

final class SpotifyScopes
{
    public const array PLAYLIST = [
        'playlist-read-private',
        'playlist-read-collaborative',
        'playlist-modify-private',
        'playlist-modify-public',
    ];

    public const array PLAYBACK = [
        'user-read-playback-state',
        'user-modify-playback-state',
    ];

    /**
     * @return list<string>
     */
    public static function control(): array
    {
        return [...self::PLAYLIST, ...self::PLAYBACK];
    }

    public static function lacksPlayback(?string $stored): bool
    {
        $granted = preg_split('/[\s,]+/', (string) $stored, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_diff(self::PLAYBACK, $granted) !== [];
    }
}
