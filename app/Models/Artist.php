<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @mixin IdeHelperArtist
 */
class Artist extends Model
{
    use HasFactory, ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)->withTimestamps();
    }

    public static function fromSpotify(object $spotifyArtist): Artist
    {
        $artist = Artist::whereSpotifyId($spotifyArtist->id)->first();
        if ($artist) {
            return $artist;
        }
        $artist = new Artist;
        $artist->spotify_id = $spotifyArtist->id;
        $artist->name = $spotifyArtist->name;
        $artist->save();
        return $artist;
    }

    public function toApi(): array
    {
        return [
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
        ];
    }
}
