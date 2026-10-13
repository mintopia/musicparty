<?php

namespace App\Events;

use App\Models\Party;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebPlayerTokenRefreshed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Party $party, protected string $accessToken) {}

    public function broadcastAs(): string
    {
        return 'WebPlayerTokenRefreshed';
    }

    /** @return array{accessToken: string} */
    public function broadcastWith(): array
    {
        return ['accessToken' => $this->accessToken];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("webplayer.{$this->party->code}");
    }
}
