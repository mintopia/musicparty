<?php

namespace App\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @mixin IdeHelperModSetting
 */
class ModSetting extends Model implements Sortable
{
    use HasFactory;
    use SortableTrait;

    protected $casts = [
        'default' => SettingValue::class,
        'type' => SettingType::class,
    ];

    public function buildSortQuery(): Builder
    {
        return static::query()->where('mod_id', $this->mod_id);
    }

    public function mod(): BelongsTo
    {
        return $this->belongsTo(Mod::class);
    }

    public function partysettings(): HasMany
    {
        return $this->hasMany(PartyModSetting::class, 'mod_setting_id');
    }

    public function isRequired(): bool
    {
        return str_contains($this->validation ?? '', 'required');
    }
}
