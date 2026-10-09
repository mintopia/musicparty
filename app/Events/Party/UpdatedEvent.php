<?php

namespace App\Events\Party;

use App\Models\Party;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class UpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(protected string $partyCode) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $party = Party::query()->where('code', $this->partyCode)->firstOrFail();

        return (array) $party->getState();
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
