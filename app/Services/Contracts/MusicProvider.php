<?php

namespace App\Services\Contracts;

use App\Services\Music\Data\Album;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\Artist;
use App\Services\Music\Data\Page;
use App\Services\Music\Data\Playlist;
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
     * @return Page<Track>
     */
    public function searchTracks(string $query, int $page, int $perPage): Page;

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
}
