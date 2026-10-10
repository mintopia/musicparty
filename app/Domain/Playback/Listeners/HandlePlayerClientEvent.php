<?php

namespace App\Domain\Playback\Listeners;

use App\Domain\Playback\Jobs\ProcessPlayerFrame;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

class HandlePlayerClientEvent
{
    public const CHANNEL_PATTERN = '/^private-player\.([A-Za-z0-9]+)$/';

    public function handle(MessageReceived $received): void
    {
        $envelope = json_decode($received->message, true);

        if (! is_array($envelope) || ! is_string($envelope['channel'] ?? null)
            || ! preg_match(self::CHANNEL_PATTERN, $envelope['channel'], $channel)) {
            return;
        }

        $event = $envelope['event'] ?? null;

        if (! is_string($event) || ! str_starts_with($event, 'client-')) {
            return;
        }

        if (app(ChannelManager::class)->find($envelope['channel'])?->find($received->connection) === null) {
            return;
        }

        $frame = $envelope['data'] ?? null;
        $code = strtoupper($channel[1]);

        if (strlen($received->message) > (int) config('musicparty.player_frames.max_bytes')
            || ! is_array($frame) || $frame === []
            || ! RateLimiter::attempt(
                'player-frames:'.$code,
                (int) config('musicparty.player_frames.max_per_minute'),
                static fn (): bool => true,
            )) {
            Cache::add(ProcessPlayerFrame::DISCARDED_COUNTER, 0);
            Cache::increment(ProcessPlayerFrame::DISCARDED_COUNTER);

            return;
        }

        ProcessPlayerFrame::enqueue($code, $frame);
    }
}
