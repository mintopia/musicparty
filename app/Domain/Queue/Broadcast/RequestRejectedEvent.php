<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RequestRejectedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $memberId,
        protected string $providerTrackId,
        protected string $reason,
    ) {}

    /**
     * @return array{provider_track_id: string, reason: string}
     */
    public function broadcastWith(): array
    {
        return ['provider_track_id' => $this->providerTrackId, 'reason' => $this->reason];
    }

    public function broadcastAs(): string
    {
        return 'request.rejected';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.member.{$this->memberId}")];
    }
}
