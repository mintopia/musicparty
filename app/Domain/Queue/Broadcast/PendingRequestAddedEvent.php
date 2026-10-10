<?php

namespace App\Domain\Queue\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PendingRequestAddedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $requestId,
        protected string $title,
        /** @var list<string> */
        protected array $artists,
        protected int $requesterMemberId,
    ) {}

    /**
     * @return array{request_id: int, title: string, artists: list<string>, requested_by_member_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'request_id' => $this->requestId,
            'title' => $this->title,
            'artists' => $this->artists,
            'requested_by_member_id' => $this->requesterMemberId,
        ];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.moderators")];
    }
}
