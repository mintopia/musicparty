<?php

namespace App\Services\Playback;

use App\Models\Party;
use Illuminate\Support\Facades\Cache;

final class PartyDashboardCache
{
    public const int READ_SECONDS = 10;

    public static function playbackKey(Party $party): string
    {
        return "party.{$party->id}.dashboard.playback";
    }

    public static function devicesKey(Party $party): string
    {
        return "party.{$party->id}.dashboard.devices";
    }

    public static function forget(Party $party): void
    {
        Cache::forget(self::playbackKey($party));
        Cache::forget(self::devicesKey($party));
    }
}
