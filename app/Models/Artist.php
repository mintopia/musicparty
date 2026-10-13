<?php

namespace App\Models;

use App\Models\Traits\ToString;
use App\Services\Music\Data\ArtistRef;
use Database\Factories\ArtistFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $provider_id
 * @property string $provider_artist_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Song> $songs
 * @property-read int|null $songs_count
 *
 * @method static \Database\Factories\ArtistFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereSpotifyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Artist whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Artist extends Model
{
    /** @use HasFactory<ArtistFactory> */
    use HasFactory;

    use ToString;

    public function toStringName(): string
    {
        return $this->name;
    }

    /** @return BelongsToMany<Song, $this> */
    public function songs(): BelongsToMany
    {
        return $this->belongsToMany(Song::class)->withTimestamps();
    }

    public static function fromRef(string $providerId, ArtistRef $ref): Artist
    {
        $artist = Artist::where(['provider_id' => $providerId, 'provider_artist_id' => $ref->id])->first();
        if ($artist) {
            return $artist;
        }
        try {
            $artist = new Artist;
            $artist->provider_id = $providerId;
            $artist->provider_artist_id = $ref->id;
            $artist->name = $ref->name;
            $artist->save();
        } catch (UniqueConstraintViolationException) {
            $artist = Artist::where(['provider_id' => $providerId, 'provider_artist_id' => $ref->id])->firstOrFail();
        }

        return $artist;
    }

    /** @return array{provider_artist_id: string, name: string} */
    public function toApi(): array
    {
        return [
            'provider_artist_id' => $this->provider_artist_id,
            'name' => $this->name,
        ];
    }
}
