<?php

namespace App\Domain\Party\Models;

use App\Domain\Identity\Models\User;
use Carbon\Carbon;
use Database\Factories\PartyLogEntryFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $party_id
 * @property int|null $user_id
 * @property string|null $system_actor
 * @property string $action
 * @property string|null $subject
 * @property array<string, mixed>|null $details
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[UseFactory(PartyLogEntryFactory::class)]
class PartyLogEntry extends Model
{
    /** @use HasFactory<PartyLogEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(fn (): bool => false);
        static::deleting(fn (): bool => false);
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
