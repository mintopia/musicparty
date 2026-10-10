<?php

namespace App\Domain\Party\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class PartyLogEntryAddedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
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

    public function broadcastAs(): string
    {
        return 'party_log.entry_added';
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("party.{$this->partyCode}.moderators")];
    }
}
