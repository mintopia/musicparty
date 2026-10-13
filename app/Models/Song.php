<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Database\Factories\SongFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * @property int $id
 * @property string $spotify_id
 * @property string $name
 * @property int $length
 * @property int $album_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Album $album
 * @property-read Collection<int, Artist> $artists
 * @property-read int|null $artists_count
 * @property-read Collection<int, Party> $parties
 * @property-read int|null $parties_count
 * @property-read Collection<int, PlayedSong> $played
 * @property-read int|null $played_count
 * @property-read Collection<int, UpcomingSong> $upcoming
 * @property-read int|null $upcoming_count
 *
 * @method static \Database\Factories\SongFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereAlbumId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereLength($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Song whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Song extends Model
{
    /** @use HasFactory<SongFactory> */
    use HasFactory;

    use ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    /** @return HasMany<UpcomingSong, $this> */
    public function upcoming(): HasMany
    {
        return $this->hasMany(UpcomingSong::class);
    }

    /** @return HasMany<PlayedSong, $this> */
    public function played(): HasMany
    {
        return $this->hasMany(PlayedSong::class);
    }

    /** @return BelongsTo<Album, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /** @return HasMany<Party, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    /** @return BelongsToMany<Artist, $this> */
    public function artists(): BelongsToMany
    {
        return $this->belongsToMany(Artist::class)->withTimestamps();
    }

    public static function fromSpotify(stdClass $track): Song
    {
        $song = Song::whereSpotifyId($track->id)->first();
        if ($song) {
            return $song;
        }
        $album = Album::fromSpotify($track->album);
        $artists = array_map(fn (stdClass $trackArtist) => Artist::fromSpotify($trackArtist), $track->artists);

        try {
            return DB::transaction(function () use ($track, $album, $artists): Song {
                $song = new Song;
                $song->spotify_id = $track->id;
                $song->name = $track->name;
                $song->length = $track->duration_ms;
                $song->album()->associate($album);
                $song->save();
                foreach ($artists as $artist) {
                    $song->artists()->attach($artist);
                }

                return $song;
            });
        } catch (UniqueConstraintViolationException) {
            return Song::whereSpotifyId($track->id)->firstOrFail();
        }
    }

    /** @return array{spotify_id: string, name: string, album: array{spotify_id: string, name: string, image_url: string}, artists: array<int, array{spotify_id: string, name: string}>, length: int} */
    public function toApi(): array
    {
        return [
            'spotify_id' => $this->spotify_id,
            'name' => $this->name,
            'album' => $this->album->toApi(),
            'artists' => $this->artists->map(fn (Artist $artist) => $artist->toApi())->toArray(),
            'length' => $this->length,
        ];
    }
}
