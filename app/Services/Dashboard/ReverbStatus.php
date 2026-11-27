<?php

namespace App\Services\Dashboard;

use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Throwable;

class ReverbStatus
{
    public function reachable(): bool
    {
        $broadcaster = $this->broadcaster();
        if ($broadcaster === null) {
            return false;
        }

        try {
            $broadcaster->getPusher()->get('/channels');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Null when Reverb cannot say.
     */
    public function subscribers(string $channel): ?int
    {
        try {
            $info = $this->broadcaster()?->getPusher()->getChannelInfo($channel, ['info' => 'subscription_count']);
        } catch (Throwable) {
            return null;
        }

        return isset($info->subscription_count) ? (int) $info->subscription_count : (isset($info->occupied) ? 0 : null);
    }

    protected function broadcaster(): ?PusherBroadcaster
    {
        $broadcaster = Broadcast::connection();

        return $broadcaster instanceof PusherBroadcaster ? $broadcaster : null;
    }
}
