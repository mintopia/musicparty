<?php

namespace App\Models;

use Database\Factories\PlayedSongFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $song_id
 * @property int $party_id
 * @property Carbon $played_at
 * @property int $likes
 * @property int $dislikes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $upcoming_song_id
 * @property int $rating
 * @property string|null $relinked_from
 * @property-read Party $party
 * @property-read Collection<int, SongRating> $ratings
 * @property-read int|null $ratings_count
 * @property-read Song $song
 * @property-read UpcomingSong|null $upcoming
 *
 * @method static \Database\Factories\PlayedSongFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereDislikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereLikes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong wherePlayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereRelinkedFrom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlayedSong whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PlayedSong extends Model
{
    /** @use HasFactory<PlayedSongFactory> */
    use HasFactory;

    protected $casts = [
        'played_at' => 'datetime',
    ];

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<Song, $this> */
    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    /** @return BelongsTo<UpcomingSong, $this> */
    public function upcoming(): BelongsTo
    {
        return $this->belongsTo(UpcomingSong::class, 'upcoming_song_id');
    }

    /** @return HasMany<SongRating, $this> */
    public function ratings(): HasMany
    {
        return $this->hasMany(SongRating::class);
    }

    public function findRequestedSong(): ?UpcomingSong
    {
        if ($this->upcoming_song_id && $this->upcoming) {
            return $this->upcoming;
        }

        $ids = [$this->song->provider_track_id];
        if ($this->relinked_from) {
            $ids[] = $this->relinked_from;
        }
        $cutoff = UpcomingSong::where('party_id', $this->party_id)
            ->has('played')
            ->orderByDesc('queued_at')
            ->orderByDesc('id')
            ->first();
        $upcoming = UpcomingSong::where('party_id', $this->party_id)
            ->whereNotNull('queued_at')
            ->doesntHave('played')
            ->whereHas('song', fn ($query) => $query->whereIn('provider_track_id', $ids))
            ->when($cutoff, fn ($query, UpcomingSong $cutoff) => $query->where(fn ($after) => $after
                ->where('queued_at', '>', $cutoff->queued_at)
                ->orWhere(fn ($tie) => $tie->where('queued_at', $cutoff->queued_at)->where('id', '>', $cutoff->id))))
            ->orderBy('queued_at')
            ->orderBy('id')
            ->first();
        if ($upcoming) {
            $this->upcoming()->associate($upcoming);
        }

        return $upcoming;
    }

    public function like(User $user): ?SongRating
    {
        return $this->addRating($user, 1);
    }

    public function dislike(User $user): ?SongRating
    {
        return $this->addRating($user, -1);
    }

    protected function addRating(User $user, int $value): ?SongRating
    {
        $rating = $this->ratings()->whereUserId($user->id)->first();
        if ($rating && $rating->value !== $value) {
            $rating->delete();

            return null;
        }
        if (! $rating) {
            $rating = new SongRating;
            $rating->song()->associate($this);
            $rating->user()->associate($user);
        }
        $rating->value = $value;
        $rating->save();

        return $rating;
    }

    public function updateRating(): void
    {
        $this->rating = (int) $this->ratings()->sum('value');
        if ($this->isDirty()) {
            $this->save();
        }
    }

    /** @return array{provider_track_id: string, name: string, album: array{provider_album_id: string, name: string, image_url: string}, artists: array<int, array{provider_artist_id: string, name: string}>, length: int, id: int, rating: int, played_at: string, user: string|null} */
    public function toApi(): array
    {
        $data = $this->song->toApi();
        $data['id'] = $this->id;
        $data['rating'] = (int) $this->rating;
        $data['played_at'] = $this->played_at->toIso8601String();
        $data['user'] = $this->upcoming->user->nickname ?? null;

        return $data;
    }
}
