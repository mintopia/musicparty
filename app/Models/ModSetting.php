<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @property int $id
 * @property int $mod_id
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property SettingType $type
 * @property bool $encrypted
 * @property bool $private
 * @property string|null $validation
 * @property mixed|null $default
 * @property int $order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Mod $mod
 * @property-read Collection<int, PartyModSetting> $partysettings
 * @property-read int|null $partysettings_count
 *
 * @method static \Database\Factories\ModSettingFactory factory($count = null, $state = [])
 * @method static Builder<static>|ModSetting newModelQuery()
 * @method static Builder<static>|ModSetting newQuery()
 * @method static Builder<static>|ModSetting ordered(string $direction = 'asc')
 * @method static Builder<static>|ModSetting query()
 * @method static Builder<static>|ModSetting whereCode($value)
 * @method static Builder<static>|ModSetting whereCreatedAt($value)
 * @method static Builder<static>|ModSetting whereDefault($value)
 * @method static Builder<static>|ModSetting whereDescription($value)
 * @method static Builder<static>|ModSetting whereEncrypted($value)
 * @method static Builder<static>|ModSetting whereId($value)
 * @method static Builder<static>|ModSetting whereModId($value)
 * @method static Builder<static>|ModSetting whereName($value)
 * @method static Builder<static>|ModSetting whereOrder($value)
 * @method static Builder<static>|ModSetting wherePrivate($value)
 * @method static Builder<static>|ModSetting whereType($value)
 * @method static Builder<static>|ModSetting whereUpdatedAt($value)
 * @method static Builder<static>|ModSetting whereValidation($value)
 *
 * @mixin \Eloquent
 */
class ModSetting extends Model implements Sortable
{
    use HasFactory;
    use SortableTrait;

    protected $casts = [
        'encrypted' => 'boolean',
        'private' => 'boolean',
        'default' => SettingValue::class,
        'type' => SettingType::class,
    ];

    public function buildSortQuery(): Builder
    {
        return static::query()->where('mod_id', $this->mod_id);
    }

    /** @return BelongsTo<Mod, $this> */
    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    /** @return HasMany<PartyModSetting, $this> */
    public function partysettings(): HasMany
    {
        return $this->hasMany(PartyModSetting::class, 'mod_setting_id');
    }

    public function isRequired(): bool
    {
        return str_contains($this->validation ?? '', 'required');
    }
}
