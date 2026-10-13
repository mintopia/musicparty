<?php

namespace App\Models;

use App\Services\ModDataStore;
use Database\Factories\PartyMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $role_id
 * @property int $party_id
 * @property bool $canvote
 * @property bool $banned
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Party $party
 * @property-read PartyMemberRole $role
 * @property-read User $user
 *
 * @method static \Database\Factories\PartyMemberFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereBanned($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCanvote($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereRoleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMember whereUserId($value)
 *
 * @mixin \Eloquent
 */
class PartyMember extends Model
{
    /** @use HasFactory<PartyMemberFactory> */
    use HasFactory;

    protected $casts = [
        'canvote' => 'boolean',
        'banned' => 'boolean',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PartyMemberRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(PartyMemberRole::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function trustScore(): float
    {
        return (float) app(ModDataStore::class)->get($this, 'trust', 'trustscore', 0);
    }

    public function setTrustScore(float $score): void
    {
        app(ModDataStore::class)->set($this, 'trust', 'trustscore', $score);
    }
}
