<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @mixin IdeHelperUpcomingSong
 */
class UpcomingSong extends Model
{
    use HasFactory;

    protected $casts = [
        'queued_at' => 'datetime',
    ];

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function song(): BelongsTo
    {
        return $this->belongsTo(Song::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toApi(): array
    {
        $data = $this->song->toApi();
        $data['score'] = $this->score;
        $data['queued_at'] = $this->queued_at ? $this->queued_at->toIso8601String() : null;
        $data['user'] = $this->user->name ?? null;
        return $data;
    }

    public function upvote(User $user): ?Vote
    {
        return $this->addVote($user, 1);
    }

    public function downvote(User $user): ?Vote
    {
        if (!$this->party->downvotes) {
            return null;
        }
        return $this->addVote($user, -1);
    }

    protected function addVote(User $user, int $value): Vote
    {
        $vote = $this->votes()->whereUserId($user->id)->first();
        if (!$vote) {
            $vote = new Vote();
            $vote->upcomingSong()->associate($this);
            $vote->user()->associate($user);
        }
        $vote->value = $value;
        $vote->save();
        return $vote;
    }

    public function updateScore(): void
    {
        $this->score = $this->votes()->sum('value');
        if ($this->isDirty()) {
            $this->save();
        }
    }
}
