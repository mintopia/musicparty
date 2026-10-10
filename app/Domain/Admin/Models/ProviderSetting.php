<?php

namespace App\Domain\Admin\Models;

use App\Casts\SettingValue;
use App\Enums\SettingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @mixin IdeHelperProviderSetting
 */
class ProviderSetting extends Model
{
    use HasFactory;

    protected $casts = [
        'value' => SettingValue::class,
        'type' => SettingType::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (ProviderSetting $setting): void {
            $setting->order ??= (int) static::query()
                ->where('provider_id', $setting->provider_id)
                ->where('provider_type', $setting->provider_type)
                ->max('order') + 1;
        });
    }

    /**
     * @param  Builder<ProviderSetting>  $query
     * @return Builder<ProviderSetting>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order');
    }

    public function provider(): MorphTo
    {
        return $this->morphTo();
    }

    public function isRequired(): bool
    {
        return str_contains($this->validation ?? '', 'required');
    }
}
