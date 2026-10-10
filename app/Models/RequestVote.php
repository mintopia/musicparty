<?php

namespace App\Models;

use Database\Factories\RequestVoteFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class RequestVote extends Model
{
    /** @use HasFactory<RequestVoteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /**
     * @return BelongsTo<TrackRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(TrackRequest::class, 'track_request_id');
    }

    /**
     * @return BelongsTo<PartyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(PartyMember::class, 'party_member_id');
    }
}
