<?php

namespace App\Listeners;

use App\Jobs\ApplyPushedPlaybackState;
use App\Jobs\RequestEncoreState;
use App\Services\Playback\EncoreConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\ChannelRemoved;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

class DispatchPushedPlaybackState
{
    private const int TOUCH_THROTTLE_SECONDS = 5;

    private const string ENCORE_PREFIX = 'private-encore.';

    /** @var array<string, string> */
    private static array $partyCodeByConnectionId = [];

    /** @var array<string, int> */
    private static array $lastTouch = [];

    public static function reset(): void
    {
        self::$partyCodeByConnectionId = [];
        self::$lastTouch = [];
    }

    public function handleChannelRemoved(ChannelRemoved $event): void
    {
        $name = $event->channel->name();
        if (! str_starts_with($name, self::ENCORE_PREFIX)) {
            return;
        }

        $code = substr($name, strlen(self::ENCORE_PREFIX));
        self::$partyCodeByConnectionId = array_filter(self::$partyCodeByConnectionId, fn (string $connected): bool => $connected !== $code);
        unset(self::$lastTouch[$code]);
        Cache::forget(EncoreConnection::seenKey($code));
    }

    /**
     * Runs inside the Reverb process: parses only the channel and event name, and leaves all other work to the queued job.
     */
    public function handle(MessageReceived $event): void
    {
        $frame = json_decode($event->message, true);
        if (! is_array($frame)) {
            return;
        }

        if (self::$partyCodeByConnectionId !== []) {
            $this->touchEncoreConnection($event->connection->id());
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

        if (! $this->isSubscribed($event->connection, $channel)) {
            Log::debug('Dropped client event from a connection not subscribed to the channel', [
                'connection' => $event->connection->id(),
                'channel' => $channel,
            ]);

            return;
        }

        ApplyPushedPlaybackState::dispatch($channel, $name, $frame['data'] ?? null);
    }

    /**
     * Reverb raises MessageReceived even for client events it refuses, so membership is checked here.
     */
    private function isSubscribed(Connection $connection, string $channel): bool
    {
        return app(ChannelManager::class)->for($connection->app())->find($channel)?->subscribed($connection) ?? false;
    }

    /**
     * Reverb dispatches MessageReceived before it authorises a subscription, so the Pusher signature is checked here too.
     */
    private function requestSnapshotOnEncoreSubscribe(Connection $connection, mixed $data): void
    {
        $data = is_string($data) ? json_decode($data, true) : $data;
        $channel = is_array($data) ? ($data['channel'] ?? null) : null;
        $auth = is_array($data) ? ($data['auth'] ?? null) : null;
        $prefix = self::ENCORE_PREFIX;

        if (! is_string($channel) || ! is_string($auth) || ! str_starts_with($channel, $prefix) || strlen($channel) === strlen($prefix)) {
            return;
        }

        $expected = hash_hmac('sha256', "{$connection->id()}:{$channel}", $connection->app()->secret());
        if (hash_equals($expected, Str::after($auth, ':'))) {
            $code = substr($channel, strlen($prefix));
            self::$partyCodeByConnectionId[$connection->id()] = $code;
            $this->touch($code);
            RequestEncoreState::dispatch($code);
        }
    }

    private function touchEncoreConnection(string $connectionId): void
    {
        $code = self::$partyCodeByConnectionId[$connectionId] ?? null;
        if ($code !== null) {
            $this->touch($code);
        }
    }

    private function touch(string $code): void
    {
        $now = now()->getTimestamp();
        if ($now - (self::$lastTouch[$code] ?? 0) < self::TOUCH_THROTTLE_SECONDS) {
            return;
        }

        self::$lastTouch[$code] = $now;
        Cache::put(EncoreConnection::seenKey($code), $now, EncoreConnection::seenSeconds());
    }
}
