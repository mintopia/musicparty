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

    public function __construct(protected Party $party, protected int $id)
    {
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->id,
        ];
    }

    public function broadcastOn()
    {
        return new PrivateChannel("party.{$this->party->code}");
    }
}
