<?php

namespace App\Events\Mods;

use App\Models\PartyModSetting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PartyModSettingUpdatedEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public array $changed = [];

    /**
     * Create a new event instance.
     */
    public function __construct(public PartyModSetting $partyModSetting)
    {
        $this->changed = $this->partyModSetting->getChanges();
    }
}
