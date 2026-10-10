<?php

namespace App\Domain\Mod\ArtistAlbumLimit;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use App\Domain\Mod\SettingKind;

class ArtistAlbumLimitMod implements Mod
{
    public const string ID = 'artist-album-limit';

    public function id(): string
    {
        return self::ID;
    }

    public function name(): string
    {
        return 'Artist & Album Limit';
    }

    public function description(): string
    {
        return 'Limits how many Tracks by the same artist or from the same album can wait in the Queue, and blocks an artist or album that just played from being requested again for a while.';
    }

    public function settings(): array
    {
        return [
            new SettingDefinition('max_per_artist', 'Max queued tracks per artist (0 = unlimited)', SettingKind::Integer, 2, min: 0),
            new SettingDefinition('max_per_album', 'Max queued tracks per album (0 = unlimited)', SettingKind::Integer, 0, min: 0),
            new SettingDefinition('cooldown_seconds', 'Cooldown after an artist or album plays (seconds, 0 = off)', SettingKind::Integer, 0, min: 0),
        ];
    }

    public function requestRules(): array
    {
        return [new ArtistAlbumLimitRule];
    }

    public function scoreModifiers(): array
    {
        return [];
    }

    public function decorationProviders(): array
    {
        return [];
    }

    public function scheduledActions(): array
    {
        return [];
    }

    public function listeners(): array
    {
        return [];
    }
}
