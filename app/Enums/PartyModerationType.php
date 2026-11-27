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
    case mtISRC;

    /**
     * @return Collection<string, string>
     */
    public static function getHumanReadableTypes(): Collection
    {
        return collect([
            self::mtName->name => 'Track name',
            self::mtId->name => 'Track ID',
            self::mtArtist->name => 'Artist name',
            self::mtArtistId->name => 'Artist ID',
            self::mtAlbum->name => 'Album name',
            self::mtAlbumId->name => 'Album ID',
            self::mtISRC->name => 'ISRC code',
        ]);
    }

    public function getHumanReadableName(): string
    {
        $lookup = self::getHumanReadableTypes();

        return $lookup[$this->name] ?? 'Unknown';
    }
}
