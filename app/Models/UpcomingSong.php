<?php

namespace App\Models;

use App\Exceptions\VoteException;
use Database\Factories\UpcomingSongFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $party_id
 * @property int $song_id
 * @property Carbon|null $not_before
 * @property Carbon|null $flagged_at
 * @property int $score
 * @property int $score_adjustment
 * @property string|null $fallback_override
 * @property string|null $css_classes
 * @property int $upvotes
 * @property int $downvotes
 * @property Carbon|null $queued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $user_id
 * @property-read Party $party
 * @property-read PlayedSong|null $played
 * @property-read Song $song
 * @property-read User|null $user
 * @property-read Collection<int, Vote> $votes
 * @property-read int|null $votes_count
 *
 * @method static \Database\Factories\UpcomingSongFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereCssClasses($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereDownvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereFallbackOverride($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereNotBefore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereQueuedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereScore($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereScoreAdjustment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUpvotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereUserId($value)
 *
 * @mixin \Eloquent
 */
class UpcomingSong extends Model
{
    /** @use HasFactory<UpcomingSongFactory> */
    use HasFactory;

    protected $casts = [
        'queued_at' => 'datetime',
        'not_before' => 'datetime',
        'flagged_at' => 'datetime',
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

    /** @return HasMany<Vote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasOne<PlayedSong, $this> */
    public function played(): HasOne
    {
        return $this->hasOne(PlayedSong::class);
    }

    /** @return array{provider_track_id: string, name: string, album: array{provider_album_id: string, name: string, image_url: string}, artists: array<int, array{provider_artist_id: string, name: string}>, length: int, score: int, queued_at: string|null, user: string|null, fallback_name: string, css_classes: string} */
    public function toApi(): array
    {
        $data = $this->song->toApi();
        $data['score'] = $this->score;
        $data['queued_at'] = $this->queued_at ? $this->queued_at->toIso8601String() : null;
        $data['user'] = $this->user->nickname ?? null;
        $data['fallback_name'] = $this->fallback_override ?? 'Fallback Track';
        $data['css_classes'] = $this->css_classes ?? '';

        return $data;
    }

    public function upvote(User $user): ?Vote
    {
        return $this->addVote($user, 1);
    }

    public function downvote(User $user): ?Vote
    {
        if (! $this->party->downvotes) {
            return null;
        }

        return $this->addVote($user, -1);
    }

    protected function addVote(User $user, int $value): Vote
    {
        $member = $this->party->getMember($user);
        if ($member->role->code === 'banned') {
            throw new VoteException('You are banned from voting for songs');
        }
        // Check downvotes
        if ($value < 0) {
            $this->party->checkDownvotesForUser($user);
        }
        $vote = $this->votes()->whereUserId($user->id)->first();
        if (! $vote) {
            try {
                $vote = new Vote;
                $vote->upcomingSong()->associate($this);
                $vote->user()->associate($user);
                $vote->value = $value;
                $vote->save();
            } catch (UniqueConstraintViolationException) {
                $vote = $this->votes()->whereUserId($user->id)->firstOrFail();
            }
        }
        $vote->value = $value;
        $vote->save();

        return $vote;
    }

    public function updateScore(): void
    {
        $this->score = (int) $this->votes()->sum('value') + $this->score_adjustment;
        if ($this->isDirty()) {
            $this->save();
        }
    }
}
