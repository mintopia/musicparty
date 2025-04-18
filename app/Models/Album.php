<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperAlbum
 */
class Album extends Model
{
    use HasFactory;
    use ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public static function fromSpotify(object $spotifyAlbum): Album
    {
        $album = Album::whereSpotifyId($spotifyAlbum->id)->first();
        if ($album) {
            return $album;
        }
        $album = new Album();
        $album->spotify_id = $spotifyAlbum->id;
        $album->name = $spotifyAlbum->name;
        $album->image_url = $spotifyAlbum->images[0]->url;
        $album->save();
        return $album;
    }

    public function toApi(): array
    {
        return [
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'image_url' => $this->image_url,
        ];
    }
}
