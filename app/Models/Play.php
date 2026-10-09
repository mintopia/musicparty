<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PlayFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $party_id
 * @property int|null $track_request_id
 * @property int|null $party_member_id
 * @property string $provider_track_id
 * @property string $title
 * @property list<string> $artists
 * @property int $duration_ms
 * @property string|null $selection_mode
 * @property int|null $selection_score
 * @property CarbonImmutable $played_at
 * @property int|null $likes
 * @property int|null $dislikes
 * @property int|null $my_rating
 */
#[Unguarded]
class Play extends Model
{
    /** @use HasFactory<PlayFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'artists' => 'array',
            'explicit' => 'boolean',
            'duration_ms' => 'integer',
            'selection_score' => 'integer',
            'played_at' => 'immutable_datetime',
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
     * @return BelongsTo<TrackRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(TrackRequest::class, 'track_request_id');
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * @param  Builder<Play>  $query
     */
    #[Scope]
    protected function withRatingSummary(Builder $query, ?PartyMember $viewer = null): void
    {
        $query
            ->withCount([
                'ratings as likes' => fn (Builder $ratings) => $ratings->where('value', 1),
                'ratings as dislikes' => fn (Builder $ratings) => $ratings->where('value', -1),
            ])
            ->withSum(['ratings as my_rating' => fn (Builder $ratings) => $ratings->where('party_member_id', $viewer?->id)], 'value');
    }
}
