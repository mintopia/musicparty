<?php

namespace App\Services\Playback;

use App\Models\Party;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks Reverb whether Encore is subscribed to the party's channel, since Encore sends nothing while paused.
 */
class EncoreConnection
{
    private const int CACHE_SECONDS = 10;

    public function __construct(protected EncoreStateMapper $mapper) {}

    /**
     * Null when Reverb cannot say.
     */
    public function isConnected(Party $party): ?bool
    {
        $channel = $this->mapper->channelPrefix().$party->code;

        try {
            return Cache::remember("encore-connected:{$party->code}", self::CACHE_SECONDS, function () use ($channel): ?bool {
                $broadcaster = Broadcast::connection();
                if (! $broadcaster instanceof PusherBroadcaster) {
                    return null;
                }

                return (bool) ($broadcaster->getPusher()->getChannelInfo($channel)->occupied ?? false);
            });
        } catch (Throwable $e) {
            Log::warning("{$party}: Could not ask Reverb whether Encore is subscribed to {$channel}: {$e->getMessage()}");

            return null;
        }
    }
}
