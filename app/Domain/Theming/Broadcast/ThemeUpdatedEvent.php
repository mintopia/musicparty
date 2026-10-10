<?php

namespace App\Domain\Theming\Broadcast;

use App\Domain\Party\Models\Party;
use App\Domain\Theming\Actions\GetPartyTheme;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

class ThemeUpdatedEvent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;

    public string $broadcastQueue = 'broadcast';

    public function __construct(public readonly string $partyCode) {}

    public function broadcastAs(): string
    {
        return 'theme.updated';
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
