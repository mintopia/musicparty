<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Song
 *
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property string $artist
 * @property string $artist_id
 * @property string $album
 * @property string $album_id
 * @property int $length
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Song newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Song newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Song query()
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereAlbum($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereArtist($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereArtistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Song whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PlayedSongs[] $played
 * @property-read int|null $played_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\UpcomingSong[] $upcoming
 * @property-read int|null $upcoming_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Artist[] $artists
 * @property-read int|null $artists_count
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Party[] $parties
 * @property-read int|null $parties_count
 */
class Song extends Model
{
    use HasFactory;

    public function upcoming()
    {
        return $this->hasMany(UpcomingSong::class);
    }

    public function played()
    {
        return $this->hasMany(PlayedSong::class);
    }

    public function album()
    {
        return $this->belongsTo(Album::class);
    }

    public function parties()
    {
        return $this->hasMany(Party::class);
    }

    public function artists()
    {
        return $this->belongsToMany(Artist::class)->withTimestamps();
    }

    public static function fromSpotify(object $track): Song
    {
        $song = Song::whereSpotifyId($track->id)->first();
        if ($song) {
            return $song;
        }
        $album = Album::fromSpotify($track->album);
        $song = new Song;
        $song->spotify_id = $track->id;
        $song->name = $track->name;
        $song->length = $track->duration_ms;
        $song->album()->associate($album);
        $song->save();
        foreach ($track->artists as $trackArtist) {
            $artist = Artist::fromSpotify($trackArtist);
            $song->artists()->attach($artist);
        }
        return $song;
    }
}
