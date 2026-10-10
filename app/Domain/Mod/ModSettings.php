<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Data\SettingDefinition;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ModSettings
{
    public const string MASK = '********';

    /**
     * @return array<string, mixed>
     */
    public function defaults(Mod $mod): array
    {
        $defaults = [];

        foreach ($mod->settings() as $definition) {
            $defaults[$definition->key] = $definition->default;
        }

        return $defaults;
    }

    /**
     * Validate a partial update and merge it over the current plain values.
     * A masked or omitted secret keeps its current value.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $current  plain (decrypted) values
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(Mod $mod, array $input, array $current): array
    {
        $rules = [];
        $merged = $current;

        foreach ($mod->settings() as $definition) {
            $key = $definition->key;

            if ($definition->kind === SettingKind::Secret && (! array_key_exists($key, $input) || $input[$key] === self::MASK)) {
                unset($input[$key]);
            }

            $rules[$key] = $this->rulesFor($definition);
        }

        $known = array_intersect_key($input, $rules);
        $unknown = array_diff_key($input, $rules);
        $payload = $known + array_diff_key($current, $known);

        $validator = Validator::make($payload, $rules);
        $validator->after(function ($validator) use ($unknown): void {
            foreach (array_keys($unknown) as $key) {
                $validator->errors()->add((string) $key, 'Unknown setting.');
            }
        });

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        foreach ($mod->settings() as $definition) {
            if (array_key_exists($definition->key, $payload)) {
                $merged[$definition->key] = $this->cast($definition, $payload[$definition->key]);
            }
        }

        return $merged;
    }

    /**
     * @param  array<string, mixed>  $plain
     * @return array<string, mixed>
     */
    public function encrypt(Mod $mod, array $plain): array
    {
        foreach ($mod->settings() as $definition) {
            $value = $plain[$definition->key] ?? null;

            if ($definition->kind === SettingKind::Secret && is_string($value) && $value !== '') {
                $plain[$definition->key] = Crypt::encryptString($value);
            }
        }

        return $plain;
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public function resolve(Mod $mod, ?array $stored): array
    {
        $values = $this->defaults($mod);

        foreach ($mod->settings() as $definition) {
            $key = $definition->key;

            if (! is_array($stored) || ! array_key_exists($key, $stored)) {
                continue;
            }

            $value = $stored[$key];

            if ($definition->kind === SettingKind::Secret && is_string($value) && $value !== '') {
                try {
                    $value = Crypt::decryptString($value);
                } catch (DecryptException) {
                    $value = $definition->default;
                }
            }

            $values[$key] = $value;
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $plain
     * @return array<string, mixed>
     */
    public function mask(Mod $mod, array $plain): array
    {
        foreach ($mod->settings() as $definition) {
            if ($definition->kind !== SettingKind::Secret) {
                continue;
            }

            $value = $plain[$definition->key] ?? null;
            $plain[$definition->key] = is_string($value) && $value !== '' ? self::MASK : null;
        }

        return $plain;
    }

    /**
     * @return list<mixed>
     */
    private function rulesFor(SettingDefinition $definition): array
    {
        $rules = [$definition->required ? 'required' : 'nullable'];

        return array_merge($rules, match ($definition->kind) {
            SettingKind::Boolean => ['boolean'],
            SettingKind::Integer => array_values(array_filter([
                'integer',
                $definition->min !== null ? 'min:'.$definition->min : null,
                $definition->max !== null ? 'max:'.$definition->max : null,
            ])),
            SettingKind::Text, SettingKind::Secret => ['string', 'max:5000'],
            SettingKind::Choice => [Rule::in($definition->options ?? [])],
        });
    }

    private function cast(SettingDefinition $definition, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($definition->kind) {
            SettingKind::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingKind::Integer => (int) $value,
            default => $value,
        };
    }
}
