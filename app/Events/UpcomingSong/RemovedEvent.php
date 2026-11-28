<?php

namespace App\Events\UpcomingSong;

use App\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RemovedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(protected Party $party, protected int $id, protected bool $played = false) {}

    /** @return array{id: int, played: bool} */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'played' => $this->played,
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel("party.{$this->party->code}");
    }
}
