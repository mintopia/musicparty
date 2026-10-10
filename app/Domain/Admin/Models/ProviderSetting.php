<?php

namespace App\Domain\Admin\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

/**
 * @mixin IdeHelperProviderSetting
 */
class ProviderSetting extends Model implements Sortable
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use SortableTrait;

    protected $casts = [
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    /**
     * @return Builder<static>
     */
    public function buildSortQuery(): Builder
    {
        return static::query()->where('provider_id', $this->provider_id)->where('provider_type', $this->provider_type);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function provider(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRequired(): bool
    {
        return str_contains($this->validation ?? '', 'required');
    }
}
