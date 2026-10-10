<?php

namespace App\Domain\Membership\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class MemberBannedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $memberId,
    ) {}

    /**
     * @return array{member_id: int}
     */
    public function broadcastWith(): array
    {
        return ['member_id' => $this->memberId];
    }

    public function broadcastAs(): string
    {
        return 'member.banned';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.member.{$this->memberId}")];
    }
}
