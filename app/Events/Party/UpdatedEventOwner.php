<?php

namespace App\Events\Party;

use App\Models\Party;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdatedEventOwner implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(protected Party $party)
    {
    }

    public function broadcastWith()
    {
        return $this->party->getState(null, true);
    }

    public function broadcastAs()
    {
        return 'App\\Events\\Party\\UpdatedEvent';
    }

    public function broadcastOn()
    {
        return new PrivateChannel("party.{$this->party->code}.owner");
    }
}
