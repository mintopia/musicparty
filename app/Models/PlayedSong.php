<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\PlayedSong
 *
 * @property int $id
 * @property int $party_id
 * @property int $song_id
 * @property string $played_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs query()
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs wherePlayedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PlayedSongs whereUpdatedAt($value)
 * @mixin \Eloquent
 * @property-read \App\Models\Party $party
 * @property-read \App\Models\Song $song
 */
class PlayedSong extends Model
{
    use HasFactory;

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function party()
    {
        return $this->belongsTo(Party::class);
    }
}
