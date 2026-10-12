<?php

namespace App\Domain\Mod\Whamageddon\Models;

use App\Domain\Party\Models\Party;
use Carbon\CarbonImmutable;
use Database\Factories\WhamageddonStateFactory;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $party_id
 * @property int|null $request_id
 * @property int $accumulated_bonus
 * @property CarbonImmutable|null $last_played_at
 */
#[Unguarded]
#[UseFactory(WhamageddonStateFactory::class)]
class WhamageddonState extends Model
{
    /** @use HasFactory<WhamageddonStateFactory> */
    use HasFactory;

    protected $table = 'mod_whamageddon_states';

    protected $attributes = [
        'accumulated_bonus' => 0,
    ];

    protected function casts(): array
    {
        return [
            'accumulated_bonus' => 'integer',
            'last_played_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Party, $this>
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
