<?php

namespace App\Services\Spotify;

use App\Models\User;
use App\Support\SpotifyPayload;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * A user's own Spotify devices and playlists, read through the gateway.
 */
class SpotifyLibrary
{
    public function __construct(protected SpotifyGateway $gateway) {}

    /**
     * @return array<int, stdClass>
     */
    public function devices(User $user): array
    {
        $api = $this->gateway->client($user);
        if (! $api) {
            return [];
        }

        /** @var array<int, stdClass> $devices */
        $devices = Cache::remember("users.{$user->id}.devices", 300, function () use ($api, $user): array {
            Log::debug("{$user}: Spotify API -> getMyDevices()");

            return (array) SpotifyPayload::object($api->getMyDevices())->devices;
        });

        return $devices;
    }

    /**
     * @return array<int, stdClass>
     */
    public function playlists(User $user): array
    {
        $api = $this->gateway->client($user);
        if (! $api) {
            return [];
        }

        $playlists = [];
        $offset = 0;
        do {
            Log::debug("{$user}: Spotify API -> getMyPlaylists({$offset})");
            $result = SpotifyPayload::object($api->getMyPlaylists(['limit' => 50, 'offset' => $offset]));
            $playlists = array_merge($playlists, $result->items);
            $offset += 50;
        } while ($result->next);

        return $playlists;
    }
}
