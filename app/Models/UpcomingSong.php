<?php

namespace App\Models;

use App\Events\UpcomingSong\UpdatedEvent;
use App\Exceptions\VoteException;
use App\Mods\ModExtensions;
use Database\Factories\UpcomingSongFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * @property int $id
 * @property int $party_id
 * @property int $song_id
 * @property Carbon|null $not_before
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $flagged_at
 * @property string|null $flag_reason
 * @property int $score
 * @property int $score_adjustment
 * @property int $upvotes
 * @property int $downvotes
 * @property Carbon|null $queued_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int|null $user_id
 * @property-read AiReview|null $aiReview
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UpcomingSong whereDownvotes($value)
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
        'scheduled_for' => 'datetime',
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

    /** @return HasOne<AiReview, $this> */
    public function aiReview(): HasOne
    {
        return $this->hasOne(AiReview::class)->latestOfMany();
    }

    /** @return HasOne<PlayedSong, $this> */
    public function played(): HasOne
    {
        return $this->hasOne(PlayedSong::class);
    }

    /** @return array{provider_track_id: string, name: string, album: array{provider_album_id: string, name: string, image_url: string}, artists: array<int, array{provider_artist_id: string, name: string}>, length: int, score: int, queued_at: string|null, user: string|null, fallback_name: string, css_classes: string, decoration: array{displayName?: string, label?: string, class?: string, accent?: string}|null} */
    public function toApi(): array
    {
        $data = $this->song->toApi();
        $data['score'] = $this->score;
        $data['queued_at'] = $this->queued_at ? $this->queued_at->toIso8601String() : null;
        $data['scheduled_for'] = $this->scheduled_for?->toIso8601String();
        $data['user'] = $this->user->nickname ?? null;
        $data['user_id'] = $this->user_id;
        $decoration = app(ModExtensions::class)->decorationFor($this->party, $this->song, $this);
        $data['fallback_name'] = $decoration->displayName ?? 'From Fallback Playlist';
        $data['css_classes'] = $decoration->cssClass ?? '';
        $data['decoration'] = $decoration?->toArray();

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
        if ($this->flagged_at !== null) {
            throw new VoteException('This request is awaiting approval and cannot be voted on');
        }
        $member = $this->party->getMember($user);
        if ($member->role->code === 'banned') {
            throw new VoteException('You are banned from voting for songs');
        }

        return Cache::lock("vote-user:{$user->id}", 10)->block(5, function () use ($user, $value): Vote {
            $existing = $this->votes()->whereUserId($user->id)->first();
            if ($value < 0 && $existing?->value === $value) {
                return $existing;
            }
            if ($value < 0) {
                $this->party->checkDownvotesForUser($user);
            }
            $attributes = ['user_id' => $user->id];
            try {
                $vote = $this->votes()->updateOrCreate($attributes, ['value' => $value]);
            } catch (UniqueConstraintViolationException) {
                $vote = $this->votes()->updateOrCreate($attributes, ['value' => $value]);
            }
            if ($value < 0) {
                DownvoteLog::create(['party_id' => $this->party_id, 'user_id' => $user->id]);
            }

            return $vote;
        });
    }

    public function updateScore(): void
    {
        $score = Vote::query()
            ->selectRaw('COALESCE(SUM(value), 0) + upcoming_songs.score_adjustment')
            ->whereColumn('votes.upcoming_song_id', 'upcoming_songs.id');
        $previous = static::query()->whereKey($this->id)->value('score');
        static::query()->whereKey($this->id)->update(['score' => $score]);
        $stored = static::query()->whereKey($this->id)->firstOrFail(['score', 'score_adjustment']);
        $this->forceFill(['score' => $stored->score, 'score_adjustment' => $stored->score_adjustment])->syncOriginalAttributes(['score', 'score_adjustment']);
        if ($previous !== $stored->score && $this->flagged_at === null && $this->queued_at === null) {
            UpdatedEvent::dispatch($this);
        }
    }
}
