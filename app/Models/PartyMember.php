<?php

namespace App\Models;

use App\Domain\Party\PartyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PartyRole $role
 * @property bool $banned
 *
 * @mixin IdeHelperPartyMember
 */
class PartyMember extends Model
{
    use HasFactory;

    protected $attributes = [
        'role' => 'guest',
        'banned' => false,
    ];

    protected function casts(): array
    {
        return [
            'role' => PartyRole::class,
            'banned' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
