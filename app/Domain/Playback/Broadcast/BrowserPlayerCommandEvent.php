<?php

namespace App\Domain\Playback\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class BrowserPlayerCommandEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(
        protected string $partyCode,
        protected string $providerId,
        protected string $providerTrackId,
    ) {}

    /**
     * @return array{action: string, provider_id: string, track_id: string}
     */
    public function broadcastWith(): array
    {
        return [
            'action' => 'play',
            'provider_id' => $this->providerId,
            'track_id' => $this->providerTrackId,
        ];
    }

    public function broadcastAs(): string
    {
        return 'browser-player.command';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('party.'.$this->partyCode.'.browser-player')];
    }
}
