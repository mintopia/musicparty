<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Database\Factories\PartyMemberRoleFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PartyMember> $member
 * @property-read int|null $member_count
 *
 * @method static \Database\Factories\PartyMemberRoleFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PartyMemberRole whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class PartyMemberRole extends Model
{
    /** @use HasFactory<PartyMemberRoleFactory> */
    use HasFactory;

    use ToString;

    /** @return HasMany<PartyMember, $this> */
    public function member(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    protected function toStringName(): string
    {
        return $this->code;
    }
}
