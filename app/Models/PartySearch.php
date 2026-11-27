<?php

namespace App\Models;

use Database\Factories\PartySearchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $party_id
 * @property int|null $user_id
 * @property string $query
 * @property string $term
 * @property Carbon|null $created_at
 * @property-read Party $party
 */
class PartySearch extends Model
{
    /** @use HasFactory<PartySearchFactory> */
    use HasFactory;

    public const null UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['party_id', 'user_id', 'query', 'term'];

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
