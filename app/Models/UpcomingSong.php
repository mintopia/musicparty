<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\UpcomingSong
 *
 * @property int $id
 * @property int $party_id
 * @property int $song_id
 * @property int $votes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Song $song
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong query()
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereVotes($value)
 * @mixin \Eloquent
 * @property-read \App\Models\Party $party
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Vote[] $user_votes
 * @property-read int|null $user_votes_count
 * @property string|null $queued_at
 * @method static \Illuminate\Database\Eloquent\Builder|UpcomingSong whereQueuedAt($value)
 */
class UpcomingSong extends Model
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

    public function user_votes()
    {
        return $this->hasMany(Vote::class);
    }

    public function updateVotes(): UpcomingSong
    {
        $votes = $this->user_votes()->count();
        if ($votes != $this->votes) {
            $this->votes = $votes;
        }
        $this->save();
        return $this;
    }

    public function hasVoted(User $user)
    {
        foreach ($this->user_votes as $vote) {
            if ($vote->user_id == $user->id) {
                return true;
            }
        }
        return false;
    }
}
