<?php

namespace App\Services\Playback;

use App\Models\Party;
use App\Services\Music\Data\PlaybackState;

interface PushedStateMapper
{
    /**
     * Private channel name prefix, ending in the separator before the Party code, e.g. "private-encore.".
     */
    public function channelPrefix(): string;

    /**
     * Maps a whispered client event to a PlaybackState, or null when the frame carries no state to apply or is malformed.
     *
     * @param  array<string, mixed>  $data
     */
    public function map(Party $party, string $event, array $data): ?PlaybackState;
}
