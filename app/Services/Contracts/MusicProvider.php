<?php

namespace App\Services\Contracts;

use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\AlbumSummary;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\SearchOverview;
use App\Services\Music\Data\Track;

interface MusicProvider
{
    public function id(): string;

    public function displayName(): string;

    /**
     * Whether the user has linked everything this provider needs to act for them.
     */
    public function isLinked(): bool;

    /**
     * Whether the user must re-authorise this provider before it can act for them.
     */
    public function needsRelink(): bool;

    /**
     * Public web link for a track on this provider, or null when it has none.
     */
    public function trackUrl(string $trackId): ?string;

    /**
     * Resolve a Player-reported track reference to a provider_track_id, or null when unrecognised.
     */
    public function resolveTrackReference(string $reference): ?string;

    /**
     * Whether the provider reserves this track for its own use, so it can never be requested.
     */
    public function isReservedTrack(string $trackId): bool;

    /**
     * @return Page<Track>
     */
    public function searchTracks(string $query, int $page, int $perPage): Page;

    /**
     * Tracks, albums and artists matching the query from a single provider call, each limited to $limit items.
     */
    public function searchOverview(string $query, int $limit): SearchOverview;

    /**
     * @return Page<AlbumSummary>
     */
    public function searchAlbums(string $query, int $page, int $perPage): Page;

    /**
     * @return Page<Artist>
     */
    public function searchArtists(string $query, int $page, int $perPage): Page;

    public function getTrack(string $id): ?Track;

    /**
     * Like getTrack, but returns null for an id the provider rejects as malformed.
     */
    public function findTrack(string $id): ?Track;

    /**
     * @param  list<string>  $ids
     * @return list<Track>
     */
    public function getTracks(array $ids): array;

    public function getAlbum(string $id): ?Album;

    public function getArtist(string $id): ?Artist;

    /**
     * @return list<Track>
     */
    public function getArtistTopTracks(string $id): array;

    /**
     * @return Page<AlbumRef>
     */
    public function getArtistAlbums(string $id, int $page, int $perPage): Page;

    /**
     * @return list<Track>
     */
    public function getPlaylistTracks(string $playlistId): array;

    /**
     * @param  list<Track>  $tracks
     */
    public function appendToPlaylist(string $playlistId, array $tracks): void;

    /**
     * @return list<Playlist>
     */
    public function listPlaylists(): array;

    /**
     * The playlist when it exists and the linked account owns it, otherwise null.
     */
    public function ownedPlaylist(string $playlistId): ?Playlist;

    /**
     * The first playlist with exactly this name that the linked account owns.
     */
    public function findOwnedPlaylistByName(string $name): ?Playlist;

    public function createPrivatePlaylist(string $name, string $description): Playlist;

    /**
     * Removes every item from a playlist the linked account owns.
     */
    public function clearPlaylist(string $playlistId): void;

    /**
     * Provider context reference for one of its playlists, comparable with PlaybackState::$contextUri.
     */
    public function playlistContextUri(string $playlistId): string;

    /**
     * Display name for a playback context, or null when unknown.
     */
    public function contextName(string $contextUri): ?string;
}
