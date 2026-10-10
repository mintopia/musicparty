<?php

namespace App\Events\Party;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class StatsUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $stats
     */
    public string $broadcastQueue = 'broadcast';

    public function __construct(protected string $partyCode, protected array $stats) {}

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->stats;
    }

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [new PresenceChannel("party.{$this->partyCode}.members")];
    }
}
