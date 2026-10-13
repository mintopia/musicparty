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
        return $this->player === PlaybackSource::Encore ? 'command' : $this->command['type'];
    }

    /**
     * Encore relays the payload verbatim to Soloist, which only accepts commands shaped {"type":"command","command":"<name>"}.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        if ($this->player !== PlaybackSource::Encore) {
            return $this->command;
        }

        ['type' => $name] = $this->command;

        return ['type' => 'command', 'command' => $name, ...array_diff_key($this->command, ['type' => true])];
    }

    public function broadcastOn(): PrivateChannel
    {
        $prefix = $this->player === PlaybackSource::Encore ? 'encore' : 'webplayer';

        return new PrivateChannel("{$prefix}.{$this->party->code}");
    }
}
