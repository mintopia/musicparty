<?php

namespace App\Models;

use App\Domain\Queue\RequestStatus;
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
}
