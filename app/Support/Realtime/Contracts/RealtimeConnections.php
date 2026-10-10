<?php

namespace App\Support\Realtime\Contracts;

interface RealtimeConnections
{
    /**
     * Close every live realtime connection the user holds so each channel is authorised again on reconnect.
     */
    public function terminateUser(int $userId): void;
}
