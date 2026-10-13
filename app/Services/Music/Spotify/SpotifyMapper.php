<?php

namespace App\Services\Music\Spotify;

use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Device;
use App\Services\Music\Data\PlaybackSource;
use App\Services\Music\Data\PlaybackState;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\Track;
use App\Support\SpotifyPayload;
use Carbon\CarbonImmutable;
use stdClass;

final class SpotifyMapper
{
    public const PROVIDER_ID = 'spotify';

    public static function track(stdClass $payload, ?AlbumRef $fallbackAlbum = null): Track
    {
        $isLocal = (bool) ($payload->is_local ?? false);
        $playable = ($payload->is_playable ?? true) && ! $isLocal;
        $linkedFrom = isset($payload->linked_from) ? SpotifyPayload::object($payload->linked_from) : null;
        $album = isset($payload->album) ? self::albumRef(SpotifyPayload::object($payload->album)) : $fallbackAlbum;

        return new Track(
            providerId: self::PROVIDER_ID,
            id: (string) ($payload->id ?? $payload->uri),
            name: (string) $payload->name,
            durationMs: (int) ($payload->duration_ms ?? 0),
            explicit: (bool) ($payload->explicit ?? false),
            isPlayable: (bool) $playable,
            album: $album ?? new AlbumRef('', '', null),
            artists: self::artistRefs($payload->artists ?? []),
            linkedFromId: isset($linkedFrom->id) ? (string) $linkedFrom->id : null,
        );
    }

    public static function albumRef(stdClass $payload): AlbumRef
    {
        return new AlbumRef((string) $payload->id, (string) $payload->name, self::imageUrl($payload));
    }

    public static function artistRef(stdClass $payload): ArtistRef
    {
        return new ArtistRef((string) $payload->id, (string) $payload->name);
    }

    public static function album(stdClass $payload): Album
    {
        $ref = self::albumRef($payload);
        $tracks = [];
        foreach ((array) ($payload->tracks->items ?? []) as $item) {
            $tracks[] = self::track(SpotifyPayload::object($item), $ref);
        }

        return new Album(
            ref: $ref,
            artists: self::artistRefs($payload->artists ?? []),
            releaseDate: isset($payload->release_date) ? (string) $payload->release_date : null,
            totalTracks: (int) ($payload->total_tracks ?? count($tracks)),
            tracks: $tracks,
        );
    }

    public static function artist(stdClass $payload): Artist
    {
        return new Artist(self::artistRef($payload), self::imageUrl($payload));
    }

    public static function playlist(stdClass $payload): Playlist
    {
        $owner = isset($payload->owner) ? SpotifyPayload::object($payload->owner) : null;
        $tracks = isset($payload->tracks) ? SpotifyPayload::object($payload->tracks) : null;

        return new Playlist(
            id: (string) $payload->id,
            name: (string) $payload->name,
            ownerName: isset($owner->display_name) ? (string) $owner->display_name : null,
            trackCount: (int) ($tracks->total ?? 0),
        );
    }

    public static function device(stdClass $payload): Device
    {
        return new Device(
            id: isset($payload->id) ? (string) $payload->id : null,
            name: (string) $payload->name,
            type: (string) ($payload->type ?? ''),
            isActive: (bool) ($payload->is_active ?? false),
        );
    }

    public static function playbackState(stdClass $payload, PlaybackSource $source = PlaybackSource::Polling): PlaybackState
    {
        $item = isset($payload->item) ? SpotifyPayload::object($payload->item) : null;
        $isTrack = $item !== null && ($item->type ?? 'track') === 'track' && isset($item->id);
        $linkedFrom = $isTrack && isset($item->linked_from) ? SpotifyPayload::object($item->linked_from) : null;
        $device = isset($payload->device) ? SpotifyPayload::object($payload->device) : null;
        $sampledAt = isset($payload->timestamp)
            ? CarbonImmutable::createFromTimestampMs((int) $payload->timestamp)
            : CarbonImmutable::now();

        return new PlaybackState(
            isPlaying: (bool) ($payload->is_playing ?? false),
            trackId: $isTrack ? (string) $item->id : null,
            linkedFromId: isset($linkedFrom->id) ? (string) $linkedFrom->id : null,
            durationMs: $isTrack && isset($item->duration_ms) ? (int) $item->duration_ms : null,
            progressMs: (int) ($payload->progress_ms ?? 0),
            sampledAt: $sampledAt,
            shuffle: (bool) ($payload->shuffle_state ?? false),
            repeat: (string) ($payload->repeat_state ?? 'off'),
            deviceId: isset($device->id) ? (string) $device->id : null,
            deviceName: isset($device->name) ? (string) $device->name : null,
            source: $source,
        );
    }

    /**
     * @return list<ArtistRef>
     */
    private static function artistRefs(mixed $artists): array
    {
        $refs = [];
        foreach ((array) $artists as $artist) {
            $refs[] = self::artistRef(SpotifyPayload::object($artist));
        }

        return $refs;
    }

    private static function imageUrl(stdClass $payload): ?string
    {
        $images = (array) ($payload->images ?? []);
        $first = $images === [] ? null : SpotifyPayload::object(reset($images));

        return isset($first->url) ? (string) $first->url : null;
    }
}
