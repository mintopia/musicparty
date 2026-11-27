<?php

namespace App\Services\Playback;

use App\Models\Party;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether Encore is connected, since it sends nothing while paused. Trusts the Reverb-side activity signal first, then asks Reverb's channel occupancy.
 */
class EncoreConnection
{
    private const int CACHE_SECONDS = 10;

    private const int SEEN_MARGIN_SECONDS = 10;

    public static function seenSeconds(): int
    {
        return (int) config('reverb.apps.apps.0.ping_interval', 60) * 2 + self::SEEN_MARGIN_SECONDS;
    }

    public static function seenKey(string $code): string
    {
        return "encore-seen:{$code}";
    }

    public function __construct(protected EncoreStateMapper $mapper) {}

    /**
     * Null when Reverb cannot say.
     */
    public function isConnected(Party $party): ?bool
    {
        if (Cache::has(self::seenKey($party->code))) {
            return true;
        }

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
