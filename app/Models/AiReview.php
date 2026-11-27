<?php

namespace App\Models;

use App\Enums\AiReviewCriterion;
use App\Enums\AiReviewOutcome;
use Database\Factories\AiReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $party_id
 * @property int|null $user_id
 * @property int|null $party_member_id
 * @property int|null $upcoming_song_id
 * @property string $track_provider
 * @property string $track_id
 * @property string $track_title
 * @property string $track_artists
 * @property string $driver
 * @property string|null $model
 * @property string|null $endpoint_host
 * @property array<string, mixed> $request
 * @property array<string, mixed>|null $response
 * @property AiReviewOutcome $outcome
 * @property string|null $reason
 * @property float|null $score
 * @property float|null $confidence
 * @property AiReviewCriterion|null $criterion
 * @property float|null $criterion_confidence
 * @property int $latency_ms
 * @property int|null $prompt_tokens
 * @property int|null $completion_tokens
 * @property int|null $total_tokens
 * @property string|null $error_class
 * @property string|null $error_message
 * @property Carbon|null $created_at
 * @property-read Party $party
 * @property-read User|null $user
 * @property-read UpcomingSong|null $upcomingSong
 *
 * @method static \Database\Factories\AiReviewFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiReview newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiReview newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AiReview query()
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class AiReview extends Model
{
    /** @use HasFactory<AiReviewFactory> */
    use HasFactory;

    use Prunable;

    public const UPDATED_AT = null;

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'outcome' => AiReviewOutcome::class,
        'score' => 'float',
        'confidence' => 'float',
        'criterion' => AiReviewCriterion::class,
        'criterion_confidence' => 'float',
    ];

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        /** @var Builder<static> $query */
        $query = $this->newQuery()->where('created_at', '<=', now()->subDays(max(1, (int) config('musicparty.ai_review_retention_days'))));

        return $query;
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<UpcomingSong, $this> */
    public function upcomingSong(): BelongsTo
    {
        return $this->belongsTo(UpcomingSong::class);
    }
}
