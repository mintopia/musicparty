<?php

namespace App\Events\Party;

use App\Models\Party;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @var array<string, mixed> */
    protected array $state;

    public function __construct(protected Party $party)
    {
        $this->state = (array) $party->getState();
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->state;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn()
    {
        return [
            new Channel("party.{$this->party->code}"),
        ];
    }
}
