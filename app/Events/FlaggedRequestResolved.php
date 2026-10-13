<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlaggedRequestResolved implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Party $party, public int $id) {}

    public function broadcastAs(): string
    {
        return 'FlaggedRequestResolved';
    }

    /** @return array{id: int} */
    public function broadcastWith(): array
    {
        return ['id' => $this->id];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->party->code}.managers");
    }
}
