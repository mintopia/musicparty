<?php

namespace App\Events\Party;

use App\Models\Party;
use App\Services\Music\Data\PlaybackSource;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PlayerCommand implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{type: string, position_ms?: int}  $command
     */
    public function __construct(
        public Party $party,
        public PlaybackSource $player,
        public array $command,
    ) {}

    public function broadcastAs(): string
    {
        return $this->command['type'];
    }

    /** @return array{type: string, position_ms?: int} */
    public function broadcastWith(): array
    {
        return $this->command;
    }

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->player === PlaybackSource::Encore ? 'encore' : 'webplayer';

        return new PrivateChannel("{$prefix}.{$this->party->code}");
    }
}
