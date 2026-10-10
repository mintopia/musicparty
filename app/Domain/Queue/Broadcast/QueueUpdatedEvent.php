<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class QueueUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(protected string $partyCode, protected array $snapshot) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->snapshot;
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
