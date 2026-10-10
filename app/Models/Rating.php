<?php

namespace App\Models;

use App\Domain\Membership\Models\PartyMember;
use Database\Factories\RatingFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Unguarded]
class Rating extends Model
{
    /** @use HasFactory<RatingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['value' => 'integer'];
    }

    /**
     * @return BelongsTo<Play, $this>
     */
    public function play(): BelongsTo
    {
        return $this->belongsTo(Play::class);
    }

    /**
     * @return BelongsTo<PartyMember, $this>
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(PartyMember::class, 'party_member_id');
    }
}
