<?php

namespace App\Domain\Playback\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PlayerCommandEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $frame  Soloist-native command frame
     */
    public function __construct(protected string $partyCode, protected array $frame) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->frame;
    }

    public function broadcastAs(): string
    {
        return 'player.command';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('player.'.$this->partyCode)];
    }
}
