<?php

namespace App\Support\Realtime;

use App\Support\Realtime\Contracts\RealtimeConnections;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReverbRealtimeConnections implements RealtimeConnections
{
    public function terminateUser(int $userId): void
    {
        try {
            $broadcaster = Broadcast::connection('reverb');

            if ($broadcaster instanceof PusherBroadcaster) {
                $broadcaster->getPusher()->terminateUserConnections((string) $userId);
            }
        } catch (Throwable $exception) {
            Log::warning('Could not terminate realtime connections', ['user_id' => $userId, 'error' => $exception->getMessage()]);
        }
    }
}
