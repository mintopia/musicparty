<?php

namespace App\Events\Party;

use App\Domain\Theming\Actions\GetPartyTheme;
use App\Models\Party;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ThemeUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;

    public function __construct(public readonly string $partyCode) {}

    public function broadcastAs(): string
    {
        return 'ThemeUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $party = Party::query()->where('code', $this->partyCode)->firstOrFail();

        return app(GetPartyTheme::class)->handle($party);
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel("party.{$this->partyCode}"),
        ];
    }
}
