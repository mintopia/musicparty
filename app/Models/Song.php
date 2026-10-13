<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\Music\Data\AlbumRef;
use App\Services\Music\Data\ArtistRef;
use App\Services\Music\Data\Track;
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

/**
 * @property int $id
 * @property string $provider_id
 * @property string $provider_track_id
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

    public function toTrack(): Track
    {
        return new Track(
            providerId: $this->provider_id,
            id: $this->provider_track_id,
            name: $this->name,
            durationMs: (int) $this->length,
            explicit: false,
            isPlayable: true,
            album: new AlbumRef(id: $this->album->provider_album_id, name: $this->album->name, imageUrl: $this->album->image_url),
            artists: array_values($this->artists->map(fn (Artist $artist): ArtistRef => new ArtistRef(id: $artist->provider_artist_id, name: $artist->name))->all()),
        );
    }

    public static function fromTrack(Track $track): Song
    {
        $identity = ['provider_id' => $track->providerId, 'provider_track_id' => $track->id];
        $song = Song::where($identity)->first();
        if ($song) {
            return $song;
        }
        $album = Album::fromRef($track->providerId, $track->album);
        $artists = array_map(fn (ArtistRef $artist): Artist => Artist::fromRef($track->providerId, $artist), $track->artists);

        try {
            return DB::transaction(function () use ($track, $album, $artists): Song {
                $song = new Song;
                $song->provider_id = $track->providerId;
                $song->provider_track_id = $track->id;
                $song->name = $track->name;
                $song->length = $track->durationMs;
                $song->album()->associate($album);
                $song->save();
                foreach ($artists as $artist) {
                    $song->artists()->attach($artist);
                }

                return $song;
            });
        } catch (UniqueConstraintViolationException) {
            return Song::where($identity)->firstOrFail();
        }
    }

    /** @return array{provider_track_id: string, name: string, album: array{provider_album_id: string, name: string, image_url: string}, artists: array<int, array{provider_artist_id: string, name: string}>, length: int} */
    public function toApi(): array
    {
        return [
            'provider_track_id' => $this->provider_track_id,
            'name' => $this->name,
            'album' => $this->album->toApi(),
            'artists' => $this->artists->map(fn (Artist $artist) => $artist->toApi())->toArray(),
            'length' => $this->length,
        ];
    }
}
