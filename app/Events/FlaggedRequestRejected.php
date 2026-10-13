<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlaggedRequestRejected implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Party $party, public int $userId, public int $id, public string $trackName, public ?string $message) {}

    public function broadcastAs(): string
    {
        return 'FlaggedRequestRejected';
    }

    /** @return array{id: int, track: string, message: string|null, party: string} */
    public function broadcastWith(): array
    {
        return ['id' => $this->id, 'track' => $this->trackName, 'message' => $this->message, 'party' => $this->party->code];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("user.{$this->userId}");
    }
}
