<?php

namespace App\Events\Party;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class QueueUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(protected string $partyCode) {}

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['code' => $this->partyCode];
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("party.{$this->partyCode}"),
        ];
    }
}
