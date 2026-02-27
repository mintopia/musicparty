<?php

namespace App\Enums;

use Illuminate\Support\Collection;

enum PartyModerationType
{
    case mtName;
    case mtId;
    case mtArtist;
    case mtArtistId;
    case mtAlbum;
    case mtAlbumId;

    public static function getHumanReadableTypes(): Collection
    {
        return collect([
            self::mtName->name => 'Track Name',
            self::mtId->name => 'Track ID',
            self::mtArtist->name => 'Artist Name',
            self::mtArtistId->name => 'Artist ID',
            self::mtAlbum->name => 'Album Name',
            self::mtAlbumId->name => 'Album ID',
        ]);
    }

    public function getHumanReadableName(): string
    {
        $lookup = self::getHumanReadableTypes();
        return $lookup[$this->name] ?? 'Unknown';
    }
}
