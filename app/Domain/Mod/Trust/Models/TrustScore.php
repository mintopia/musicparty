<?php

namespace App\Domain\Mod\Trust\Models;

use App\Domain\Membership\Models\PartyMember;
use App\Domain\Party\Models\Party;
use Carbon\CarbonImmutable;
use Database\Factories\TrustScoreFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $party_id
 * @property int $party_member_id
 * @property float $score
 * @property CarbonImmutable $calculated_at
 */
#[Unguarded]
#[UseFactory(TrustScoreFactory::class)]
class TrustScore extends Model
{
    /** @use HasFactory<TrustScoreFactory> */
    use HasFactory;

    protected $table = 'mod_trust_scores';

    protected function casts(): array
    {
        return [
            'score' => 'float',
            'calculated_at' => 'immutable_datetime',
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
    public function member(): BelongsTo
    {
        return $this->belongsTo(PartyMember::class, 'party_member_id');
    }
}
