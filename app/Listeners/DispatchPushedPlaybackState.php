<?php

namespace App\Listeners;

use App\Jobs\ApplyPushedPlaybackState;
use App\Jobs\RequestEncoreState;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
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
        if ($name === 'pusher:subscribe') {
            $this->requestSnapshotOnEncoreSubscribe($event->connection, $frame['data'] ?? null);

            return;
        }

        if (! is_string($name) || ! is_string($channel) || ! str_starts_with($name, 'client-')) {
            return;
        }

        ApplyPushedPlaybackState::dispatch($channel, $name, $frame['data'] ?? null);
    }

    /**
     * Reverb dispatches MessageReceived before it authorises a subscription, so the Pusher signature is checked here too.
     */
    private function requestSnapshotOnEncoreSubscribe(Connection $connection, mixed $data): void
    {
        $data = is_string($data) ? json_decode($data, true) : $data;
        $channel = is_array($data) ? ($data['channel'] ?? null) : null;
        $auth = is_array($data) ? ($data['auth'] ?? null) : null;
        $prefix = 'private-encore.';

        if (! is_string($channel) || ! is_string($auth) || ! str_starts_with($channel, $prefix) || strlen($channel) === strlen($prefix)) {
            return;
        }

        $expected = hash_hmac('sha256', "{$connection->id()}:{$channel}", $connection->app()->secret());
        if (hash_equals($expected, Str::after($auth, ':'))) {
            RequestEncoreState::dispatch(substr($channel, strlen($prefix)));
        }
    }
}
