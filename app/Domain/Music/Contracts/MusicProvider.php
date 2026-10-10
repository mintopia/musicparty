<?php

namespace App\Domain\Music\Contracts;

use App\Domain\Music\Capability;
use App\Domain\Music\Data\PlaylistData;
use App\Domain\Music\Data\SearchPage;
use App\Domain\Music\Data\TrackData;
use App\Domain\Music\Exceptions\ProviderTemporaryFailure;
use App\Domain\Music\Exceptions\ProviderUnavailableException;
use App\Domain\Music\Exceptions\UnsupportedCapability;

interface MusicProvider
{
    public function id(): string;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function search(string $query, int $limit, int $offset): SearchPage;

    /**
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function getTrack(string $providerTrackId): ?TrackData;

    /**
     * @return list<PlaylistData>
     *
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function playlists(string $hostAccountId): array;

    /**
     * @return list<TrackData>
     *
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function playlistTracks(string $playlistId, string $hostAccountId): array;

    public function forgetPlaylist(string $playlistId): void;

    /**
     * The public Music Provider page for a Track, or null when no usable id is given.
     */
    public function trackUrl(?string $providerTrackId): ?string;

    /**
     * The public Music Provider page for a playlist, or null when no usable id is given.
     */
    public function playlistUrl(?string $providerPlaylistId): ?string;

    public function supports(Capability $capability): bool;

    /**
     * @param  list<string>  $providerTrackIds
     *
     * @throws UnsupportedCapability
     * @throws ProviderUnavailableException
     * @throws ProviderTemporaryFailure
     */
    public function appendToPlaylist(string $playlistId, array $providerTrackIds, string $hostAccountId): void;
}
