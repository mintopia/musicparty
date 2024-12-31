<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPlayedSong
 */
class PlayedSong extends Model
{
    use HasFactory;

    protected $casts = [
        'played_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function upcoming(): BelongsTo
    {
        return $this->belongsTo(UpcomingSong::class, 'upcoming_song_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(SongRating::class);
    }

    public function findRequestedSong(): ?UpcomingSong
    {
        if ($this->upcoming_song_id && $this->upcoming) {
            return $this->upcoming;
        }

        // Attempt to find most recent song matching this one
        $ids = [$this->song->spotify_id];
        if ($this->relinked_from) {
            $ids[] = $this->relinked_from;
        }
        $upcoming = UpcomingSong::whereHas('song', function($query) use ($ids) {
            $query->whereIn('spotify_id', $ids);
        })->doesntHave('played')->orderBy('queued_at', 'DESC')->first();
        if ($upcoming) {
            $this->upcoming()->associate($upcoming);
        }
        return $upcoming;
    }

    public function like(User $user): SongRating
    {
        return $this->addRating($user, 1);
    }

    public function dislike(User $user): SongRating
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
        if (!$rating) {
            $rating = new SongRating();
            $rating->song()->associate($this);
            $rating->user()->associate($user);
        }
        $rating->value = $value;
        $rating->save();
        return $rating;
    }

    public function updateRating(): void
    {
        $this->rating = $this->ratings()->sum('value');
        if ($this->isDirty()) {
            $this->save();
        }
    }

    public function toApi(): array
    {
        $data = $this->song->toApi();
        $data['id'] = $this->id;
        $data['rating'] = (int)$this->rating;
        $data['played_at'] = $this->played_at ? $this->played_at->toIso8601String() : null;
        $data['user'] = $this->upcoming->user->nickname ?? null;
        return $data;
    }
}
