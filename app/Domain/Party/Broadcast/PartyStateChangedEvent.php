<?php

namespace App\Domain\Party\Broadcast;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PartyStateChangedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected string $state,
    ) {}

    /**
     * @return array{state: string}
     */
    public function broadcastWith(): array
    {
        return ['state' => $this->state];
    }

    public function broadcastAs(): string
    {
        return 'party.state_changed';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("party.{$this->partyCode}")];
    }
}
