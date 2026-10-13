<?php

namespace Tests\Support;

use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Playlist;
use App\Services\Music\Data\Track;
use App\Services\Music\Fake\FakeMusicProvider;

class FakeFallbackPlaylist
{
    public static function track(string $id, bool $playable = true): Track
    {
        return new Track('fake', $id, "Track {$id}", 180000, false, $playable, new AlbumRef("album-{$id}", "Album {$id}", null), [new ArtistRef("artist-{$id}", "Artist {$id}")]);
    }

    /**
     * @param  list<string>  $ids
     */
    public static function seed(array $ids, string $playlistId = 'BACKUP'): FakeMusicProvider
    {
        return app(FakeMusicProvider::class)->withPlaylist(
            new Playlist($playlistId, 'Fallback', 'Owner', count($ids)),
            array_map(fn (string $id): Track => self::track($id), $ids),
        );
    }
}
