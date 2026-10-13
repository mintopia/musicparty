<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $played_song_id
 * @property int $user_id
 * @property int $value
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PlayedSong $song
 * @property-read User $user
 *
 * @method static \Database\Factories\SongRatingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating wherePlayedSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SongRating whereValue($value)
 *
 * @mixin \Eloquent
 */
class SongRating extends Model
{
    use HasFactory;

    /** @return BelongsTo<PlayedSong, $this> */
    public function song(): BelongsTo
    {
        return $this->belongsTo(PlayedSong::class, 'played_song_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
