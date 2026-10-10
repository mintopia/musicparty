<?php

namespace App\Domain\Mod\Models;

use App\Domain\Party\Models\Party;
use Database\Factories\PartyModFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $party_id
 * @property string $mod_id
 * @property bool $enabled
 * @property array<string, mixed>|null $settings stored values; secrets are encrypted
 */
#[UseFactory(PartyModFactory::class)]
class PartyMod extends Model
{
    /** @use HasFactory<PartyModFactory> */
    use HasFactory;

    protected $attributes = [
        'enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'settings' => 'array',
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
