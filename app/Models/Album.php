<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * @property int $id
 * @property string $name
 * @property string $spotify_id
 * @property string $image_url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Song> $songs
 * @property-read int|null $songs_count
 *
 * @method static \Database\Factories\AlbumFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereImageUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Album whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory;

    use ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    /** @return HasMany<Song, $this> */
    public function songs(): HasMany
    {
        return $this->hasMany(Song::class);
    }

    public static function fromSpotify(stdClass $spotifyAlbum): Album
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

    /** @return array{spotify_id: string, name: string, image_url: string} */
    public function toApi(): array
    {
        return [
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'image_url' => $this->image_url,
        ];
    }
}
