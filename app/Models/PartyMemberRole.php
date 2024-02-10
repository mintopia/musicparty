<?php

namespace App\Models;

use App\Models\Traits\ToString;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin IdeHelperPartyMemberRole
 */
class PartyMemberRole extends Model
{
    use HasFactory;
    use ToString;

    public function member(): HasMany
    {
        return $this->hasMany(PartyMember::class);
    }

    protected function toStringName(): string
    {
        return $this->code;
    }
}
