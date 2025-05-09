<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @mixin IdeHelperSongRating
 */
class SongRating extends Model
{
    use HasFactory;

    public function song(): BelongsTo
    {
        return $this->belongsTo(PlayedSong::class, 'played_song_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
