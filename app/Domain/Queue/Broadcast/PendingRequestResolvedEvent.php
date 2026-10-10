<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PendingRequestResolvedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $requestId,
        protected string $status,
    ) {}

    /**
     * @return array{request_id: int, status: string}
     */
    public function broadcastWith(): array
    {
        return ['request_id' => $this->requestId, 'status' => $this->status];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.moderators")];
    }
}
