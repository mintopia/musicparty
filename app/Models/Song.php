<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperSong
 */
class Song extends Model
{
    use HasFactory;
    use ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    public function played(): HasMany
    {
        return $this->hasMany(PlayedSong::class);
    }

    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    public function artists(): BelongsToMany
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
        $song = new Song();
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

    public function toApi(): array
    {
        return [
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'album' => $this->album->toApi(),
            'artists' => $this->artists->map(function (Artist $artist) {
                return $artist->toApi();
            })->toArray(),
            'length' => $this->length,
        ];
    }
}
