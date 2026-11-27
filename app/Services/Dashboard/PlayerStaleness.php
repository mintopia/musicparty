<?php

namespace App\Services\Dashboard;

use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Playback\EncoreConnection;
use Carbon\CarbonInterface;

class PlayerStaleness
{
    private const int STALE_SECONDS = 60;

    public function __construct(private readonly EncoreConnection $encore) {}

    public function isStale(Party $party): bool
    {
        $received = $party->playback_received_at;
        if ($received === null || $received->greaterThan(now()->subSeconds(self::STALE_SECONDS))) {
            return false;
        }

        return $party->playback_source !== PlaybackSource::Encore || $this->encore->isConnected($party) !== true;
    }

    public function words(?CarbonInterface $at): string
    {
        return $at?->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE) ?? 'a while';
    }
}
