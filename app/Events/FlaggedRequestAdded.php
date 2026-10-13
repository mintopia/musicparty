<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlaggedRequestAdded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $item
     */
    public function __construct(public Party $party, public array $item) {}

    public function broadcastAs(): string
    {
        return 'FlaggedRequestAdded';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->item;
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->party->code}.managers");
    }
}
