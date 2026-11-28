<?php

namespace App\Models;

use App\Services\Queue\QueueListing;
use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $upcoming_song_id
 * @property int $user_id
 * @property int $value
 * @property Carbon|null $value_changed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UpcomingSong $upcomingSong
 * @property-read User $user
 *
 * @method static \Database\Factories\VoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpcomingSongId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Vote whereValue($value)
 *
 * @mixin \Eloquent
 */
#[Fillable(['upcoming_song_id', 'user_id', 'value', 'value_changed_at'])]
class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $casts = ['value_changed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (Vote $vote): void {
            if (! $vote->exists || $vote->isDirty('value') || $vote->value_changed_at === null) {
                $vote->value_changed_at = now();
            }
        });
        $forget = fn (Vote $vote) => QueueListing::forget((int) $vote->upcomingSong()->value('party_id'));
        static::saved($forget);
        static::deleted($forget);
    }

    /** @return BelongsTo<UpcomingSong, $this> */
    public function upcomingSong(): BelongsTo
    {
        return $this->belongsTo(UpcomingSong::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
