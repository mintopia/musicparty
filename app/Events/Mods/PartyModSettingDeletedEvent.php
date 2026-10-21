<?php

namespace App\Events\Mods;

use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartyModSettingDeletedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Party $party;
    public ModSetting $setting;

    /**
     * Create a new event instance.
     */
    public function __construct(PartyModSetting $partyModSetting)
    {
        $this->setting = $partyModSetting->setting;
        $this->party = $partyModSetting->party;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
