<?php

namespace App\Listeners;

use App\Jobs\ApplyPushedPlaybackState;
use Laravel\Reverb\Events\MessageReceived;

class DispatchPushedPlaybackState
{
    /**
     * Runs inside the Reverb process: parses only the channel and event name, and leaves all other work to the queued job.
     */
    public function handle(MessageReceived $event): void
    {
        $frame = json_decode($event->message, true);
        if (! is_array($frame)) {
            return;
        }

        $name = $frame['event'] ?? null;
        $channel = $frame['channel'] ?? null;
        if (! is_string($name) || ! is_string($channel) || ! str_starts_with($name, 'client-')) {
            return;
        }

        ApplyPushedPlaybackState::dispatch($channel, $name, $frame['data'] ?? null);
    }
}
