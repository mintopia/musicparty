<?php

namespace App\Casts;

use App\Enums\SettingType;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class SettingValue implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($model->encrypted && $value !== null) {
            $value = Crypt::decrypt($value);
        }
        if (is_string($value)) {
            try {
                $unserialized = @unserialize($value);
                if ($unserialized !== false) {
                    $value = $unserialized;
                }
            } catch (\Exception $e) {
                // Do nothing, assume it's OK!
            }
        }
        switch ($model->type) {
            case SettingType::stBoolean:
                return (bool)$value;
            case SettingType::stInteger:
                return (int)$value;
            case SettingType::stFloat:
                return (float)$value;
            case SettingType::stDateTime:
                if ($value !== null && !$value instanceof CarbonImmutable) {
                    $value = CarbonImmutable::parse($value);
                }
                return $value;
            default:
                return $value;
        }
    }

    /**
     * Prepare the given value for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        $value = serialize($value);
        if ($model->encrypted && $value !== null) {
            $value = Crypt::encrypt($value);
        }
        return $value;
    }
}
