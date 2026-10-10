<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class RequestDecidedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $memberId,
        protected int $requestId,
        protected string $status,
        protected ?string $reason,
    ) {}

    /**
     * @return array{request_id: int, status: string, reason: string|null}
     */
    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->requestId,
            'status' => $this->status,
            'reason' => $this->reason,
        ];
    }

    public function broadcastAs(): string
    {
        return 'request.decided';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.member.{$this->memberId}")];
    }
}
