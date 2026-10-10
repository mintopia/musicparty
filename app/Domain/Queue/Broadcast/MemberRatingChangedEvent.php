<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class MemberRatingChangedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $memberId,
        protected int $play_id,
        protected int $value,
    ) {}

    /**
     * @return array{play_id: int, value: int}
     */
    public function broadcastWith(): array
    {
        return ['play_id' => $this->play_id, 'value' => $this->value];
    }

    public function broadcastAs(): string
    {
        return 'member.rating_changed';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.member.{$this->memberId}")];
    }
}
