<?php

namespace App\Domain\Party\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class PartyLogEntryAddedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(
        protected string $partyCode,
        protected int $entryId,
        protected string $action,
        protected ?string $subject,
    ) {}

    /**
     * @return array{id: int, action: string, subject: string|null}
     */
    public function broadcastWith(): array
    {
        return ['id' => $this->entryId, 'action' => $this->action, 'subject' => $this->subject];
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.moderators")];
    }
}
