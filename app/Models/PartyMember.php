<?php

namespace App\Models;

use App\Domain\Party\PartyRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

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

    public function holder(): User
    {
        $user = $this->user;

        return $user instanceof User ? $user : throw new LogicException("Party member {$this->id} has no user.");
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
