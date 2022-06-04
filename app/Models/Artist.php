<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Artist
 *
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Artist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist query()
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Artist whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Song[] $songs
 * @property-read int|null $songs_count
 */
class Artist extends Model
{
    use HasFactory;

    public function songs()
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
