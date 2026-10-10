<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class MemberVoteChangedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $memberId,
        protected int $request_id,
        protected int $value,
    ) {}

    /**
     * @return array{request_id: int, value: int}
     */
    public function broadcastWith(): array
    {
        return ['request_id' => $this->request_id, 'value' => $this->value];
    }

    public function broadcastAs(): string
    {
        return 'member.vote_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.member.{$this->memberId}")];
    }
}
