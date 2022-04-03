<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Album
 *
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property string $image_url
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Album whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Song[] $songs
 * @property-read int|null $songs_count
 */
class Album extends Model
{
    use HasFactory;

    public function songs()
    {
        return $this->hasMany(Song::class);
    }

    public static function fromSpotify(object $spotifyAlbum): Album
    {
        $album = Album::whereSpotifyId($spotifyAlbum->id)->first();
        if ($album) {
            return $album;
        }
        $album = new Album;
        $album->spotify_id = $spotifyAlbum->id;
        $album->name = $spotifyAlbum->name;
        $album->image_url = $spotifyAlbum->images[0]->url;
        $album->save();
        return $album;
    }
}
