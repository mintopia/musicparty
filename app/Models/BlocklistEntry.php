<?php

namespace App\Models;

use App\Domain\Queue\BlocklistMatchType;
use Database\Factories\BlocklistEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $party_id
 * @property BlocklistMatchType $match_type
 * @property string $value
 * @property bool $is_regex
 * @property bool $is_enabled
 * @property string|null $notes
 */
class BlocklistEntry extends Model
{
    /** @use HasFactory<BlocklistEntryFactory> */
    use HasFactory;

    protected $attributes = [
        'is_regex' => false,
        'is_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'match_type' => BlocklistMatchType::class,
            'is_regex' => 'boolean',
            'is_enabled' => 'boolean',
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
