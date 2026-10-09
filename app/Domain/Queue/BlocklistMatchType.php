<?php

namespace App\Domain\Queue;

enum BlocklistMatchType: string
{
    case TrackName = 'track_name';
    case TrackId = 'track_id';
    case ArtistName = 'artist_name';
    case ArtistId = 'artist_id';
    case AlbumName = 'album_name';
    case AlbumId = 'album_id';
    case Isrc = 'isrc';

    public function supportsRegex(): bool
    {
        return match ($this) {
            self::TrackName, self::ArtistName, self::AlbumName => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TrackName => 'Track name',
            self::TrackId => 'Track ID',
            self::ArtistName => 'Artist name',
            self::ArtistId => 'Artist ID',
            self::AlbumName => 'Album name',
            self::AlbumId => 'Album ID',
            self::Isrc => 'ISRC',
        };
    }
}
