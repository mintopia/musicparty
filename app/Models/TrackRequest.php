<?php

namespace App\Models;

use App\Domain\Queue\RequestStatus;
use Carbon\CarbonImmutable;
use Database\Factories\TrackRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property RequestStatus $status
 * @property list<string> $artists
 * @property int|null $score
 * @property int|null $my_vote
 * @property Carbon $updated_at
 * @property Carbon|null $decided_at
 * @property string|null $rejection_reason
 * @property int|null $likes
 * @property int|null $dislikes
 * @property int|null $my_rating
 * @property int|null $upvotes
 * @property int|null $downvotes
 * @property int|null $party_member_id
 * @property string $provider_track_id
 * @property int $duration_ms
 * @property CarbonImmutable|null $not_before
 * @property CarbonImmutable|null $up_next_at
 * @property CarbonImmutable|null $enqueued_at
 * @property CarbonImmutable|null $started_at
 * @property string|null $selection_mode
 * @property int|null $selection_score
 */
#[Unguarded]
class TrackRequest extends Model
{
    /** @use HasFactory<TrackRequestFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
            'artists' => 'array',
            'explicit' => 'boolean',
            'duration_ms' => 'integer',
            'decided_at' => 'datetime',
            'not_before' => 'immutable_datetime',
            'up_next_at' => 'immutable_datetime',
            'enqueued_at' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'selection_score' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * @return BelongsTo<PartyMember, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(PartyMember::class, 'party_member_id');
    }

    /**
     * @return HasMany<RequestVote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(RequestVote::class);
    }

    /**
     * @return HasMany<PlayRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(PlayRating::class);
    }
}
