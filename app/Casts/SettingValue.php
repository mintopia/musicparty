<?php

namespace App\Casts;

use App\Enums\SettingType;
use App\Models\PartyModSetting;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * @implements CastsAttributes<mixed, mixed>
 */
class SettingValue implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($this->isEncrypted($model) && $value !== null) {
            $value = Crypt::decrypt($value);
        }
        if (is_string($value)) {
            $unserialized = @unserialize($value);
            if ($unserialized !== false) {
                $value = $unserialized;
            }
        }
        switch ($this->typeOf($model)) {
            case SettingType::stBoolean:
                return (bool) $value;
            case SettingType::stInteger:
                return (int) $value;
            case SettingType::stFloat:
                return (float) $value;
            case SettingType::stDateTime:
                if ($value !== null && ! $value instanceof CarbonImmutable) {
                    $value = CarbonImmutable::parse($value);
                }

                return $value;
            case SettingType::stTrack:
            case SettingType::stTimeOfDay:
            case SettingType::stSecret:
                return $value === null ? null : (string) $value;
            default:
                return $value;
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $value = serialize($value);
        if ($this->isEncrypted($model)) {
            $value = Crypt::encrypt($value);
        }

        return $value;
    }

    protected function typeOf(Model $model): mixed
    {
        $owner = $model instanceof PartyModSetting ? $model->setting : $model;

        return $owner?->getAttribute('type');
    }

    protected function isEncrypted(Model $model): bool
    {
        $owner = $model instanceof PartyModSetting ? $model->setting : $model;

        return $owner !== null && ($owner->getAttribute('encrypted') || $owner->getAttribute('type') === SettingType::stSecret);
    }
}
