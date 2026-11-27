<?php

namespace App\Events;

use App\Models\Party;
use App\Services\Requests\ModerationTabs;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FlaggedRequestResolved implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $flaggedCount;

    public function __construct(public Party $party, public int $id)
    {
        $this->flaggedCount = app(ModerationTabs::class)->flaggedCount($party);
    }

    public function broadcastAs(): string
    {
        return 'FlaggedRequestResolved';
    }

    /** @return array{id: int, flaggedCount: int} */
    public function broadcastWith(): array
    {
        return ['id' => $this->id, 'flaggedCount' => $this->flaggedCount];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("party.{$this->party->code}.managers");
    }
}
