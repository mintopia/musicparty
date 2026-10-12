<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;

readonly class ChangePartyPlaybackDevice
{
    public function __invoke(Party $party, ?string $deviceId, ?string $deviceName): Party
    {
        $party->forceFill(['playback_device_id' => $deviceId, 'playback_device_name' => $deviceName])->save();

        return $party;
    }
}
