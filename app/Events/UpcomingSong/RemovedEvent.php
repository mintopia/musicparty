<?php

namespace App\Events\UpcomingSong;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RemovedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(protected string $partyCode, protected int $id) {}

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->partyCode}");
    }
}
