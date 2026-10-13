<?php

namespace App\Services;

use App\Enums\Purpose;
use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use App\Services\Music\MusicProviderFactory;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class ModSettingsStore
{
    /** @var array<int, array<string, PartyModSetting>> */
    protected array $loaded = [];

    public function value(Party $party, Mod|string $mod, string $code, mixed $default = null): mixed
    {
        $setting = $this->find($party, $mod, $code);
        if ($setting === null) {
            return $default;
        }
        if ($setting->setting->instance_only) {
            return $this->instanceValue($mod, $code) ?? $default;
        }

        return $setting->value ?? $default;
    }

    public function instanceValue(Mod|string $mod, string $code): mixed
    {
        $definition = $this->instanceDefinition($mod, $code);
        if ($definition === null || $definition->getRawOriginal('instance_value') === null) {
            return $definition?->default;
        }

        return $definition->instance_value;
    }

    public function setInstance(Mod|string $mod, string $code, mixed $value): ModSetting
    {
        $definition = $this->instanceDefinition($mod, $code);
        if ($definition === null) {
            throw new InvalidArgumentException("Unknown instance-only mod setting {$this->key($mod, $code)}");
        }
        $hasStored = $definition->getRawOriginal('instance_value') !== null;
        if ($definition->type === SettingType::stSecret && ($value === null || $value === '') && $hasStored) {
            return $definition;
        }
        $definition->instance_value = $this->validated($definition, $value, null);
        $definition->save();

        return $definition;
    }

    /**
     * Validate and normalise a value for a setting definition, throwing a ValidationException keyed by the setting code.
     */
    public function validated(ModSetting $definition, mixed $value, ?Party $party): mixed
    {
        $value = $value === '' ? null : $value;
        $rules = $this->rulesFor($definition, $party);
        Validator::make([$definition->code => $value], [$definition->code => $rules], [], [$definition->code => $definition->name])->validate();
        if ($value === null) {
            return null;
        }

        return match ($definition->type) {
            SettingType::stBoolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            SettingType::stInteger => (int) $value,
            SettingType::stFloat => (float) $value,
            SettingType::stTrack => $this->resolveTrack($party, (string) $value),
            default => $value,
        };
    }

    /**
     * @return list<mixed>
     */
    protected function rulesFor(ModSetting $definition, ?Party $party): array
    {
        $required = $definition->isRequired();
        $rules = [$required ? 'required' : 'nullable'];
        $rules = array_merge($rules, match ($definition->type) {
            SettingType::stBoolean => ['boolean'],
            SettingType::stInteger => ['integer'],
            SettingType::stFloat => ['numeric'],
            SettingType::stDateTime => ['date'],
            SettingType::stTimeOfDay => ['date_format:H:i'],
            SettingType::stTrack => [
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($party): void {
                    if (! is_string($value) || $this->resolveTrack($party, $value) === null) {
                        $fail('The :attribute must be a valid track for the Party\'s music provider.');
                    }
                },
            ],
            default => ['string'],
        });
        $extra = $definition->validation;
        if ($extra !== null && $extra !== '') {
            foreach (explode('|', $extra) as $rule) {
                if ($rule !== '' && $rule !== 'required' && $rule !== 'nullable') {
                    $rules[] = $rule;
                }
            }
        }

        return $rules;
    }

    /**
     * Resolve a track setting to a provider_track_id. A provider reference (e.g. a URI) is resolved by the Party's
     * Music Provider; a bare id is only format-checked, as there is no cheap existence lookup.
     */
    protected function resolveTrack(?Party $party, string $value): ?string
    {
        $value = trim($value);
        if (str_contains($value, ':')) {
            if ($party === null) {
                return null;
            }
            try {
                $resolved = app(MusicProviderFactory::class)->forParty($party, Purpose::Search)->resolveTrackReference($value);
            } catch (Throwable) {
                return null;
            }

            return $resolved !== '' ? $resolved : null;
        }

        return preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $value) === 1 ? $value : null;
    }

    protected function instanceDefinition(Mod|string $mod, string $code): ?ModSetting
    {
        $modCode = $mod instanceof Mod ? $mod->code : $mod;

        return ModSetting::query()
            ->where('instance_only', true)
            ->where('code', $code)
            ->whereHas('mod', fn ($query) => $query->where('code', $modCode))
            ->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentFor(Party $party): array
    {
        $enabled = $party->mods()->pluck('mods.code')->all();
        $settings = $this->load($party);
        $result = [];
        foreach (Mod::query()->whereNull('removed_at')->orderBy('name')->get() as $mod) {
            $rows = [];
            foreach ($settings as $key => $setting) {
                $definition = $setting->setting;
                if (! str_starts_with($key, $mod->code.':') || $definition->private || $definition->instance_only) {
                    continue;
                }
                $stored = $setting->exists && $setting->getRawOriginal('value') !== null;
                $rows[] = $this->presentSetting($definition, $setting->value, $stored);
            }
            $result[] = [
                'code' => $mod->code,
                'name' => $mod->name,
                'description' => $mod->description,
                'enabled' => in_array($mod->code, $enabled, true),
                'settings' => $rows,
            ];
        }

        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function presentInstance(): array
    {
        $result = [];
        $definitions = ModSetting::query()
            ->where('instance_only', true)
            ->where('private', false)
            ->whereHas('mod', fn ($query) => $query->whereNull('removed_at'))
            ->with('mod')
            ->orderBy('mod_id')
            ->orderBy('order')
            ->get();
        foreach ($definitions->groupBy('mod_id') as $group) {
            $mod = $group->firstOrFail()->mod;
            $result[] = [
                'code' => $mod->code,
                'name' => $mod->name,
                'description' => $mod->description,
                'settings' => $group->map(function (ModSetting $definition): array {
                    $stored = $definition->getRawOriginal('instance_value') !== null;

                    return $this->presentSetting($definition, $stored ? $definition->instance_value : $definition->default, $stored);
                })->values()->all(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    protected function presentSetting(ModSetting $definition, mixed $value, bool $stored): array
    {
        $secret = $definition->type === SettingType::stSecret;

        return [
            'code' => $definition->code,
            'name' => $definition->name,
            'description' => $definition->description,
            'type' => $definition->type->label(),
            'value' => $secret ? null : $this->presentValue($value),
            'has_value' => $secret ? $stored : $value !== null,
            'default' => $secret ? null : $this->presentValue($definition->default),
            'required' => $definition->isRequired(),
        ];
    }

    protected function presentValue(mixed $value): mixed
    {
        return $value instanceof CarbonInterface ? $value->toIso8601String() : $value;
    }

    public function find(Party $party, Mod|string $mod, string $code): ?PartyModSetting
    {
        return $this->load($party)[$this->key($mod, $code)] ?? null;
    }

    public function set(Party $party, Mod|string $mod, string $code, mixed $value): PartyModSetting
    {
        $setting = $this->find($party, $mod, $code);
        if ($setting === null) {
            $modCode = $mod instanceof Mod ? $mod->code : $mod;
            throw new InvalidArgumentException("Unknown mod setting {$modCode}:{$code}");
        }
        if ($setting->setting->instance_only) {
            throw new InvalidArgumentException("Mod setting {$this->key($mod, $code)} is instance-only; use setInstance()");
        }
        if ($setting->setting->type === SettingType::stSecret && ($value === null || $value === '') && $setting->exists && $setting->getRawOriginal('value') !== null) {
            return $setting;
        }
        $setting->value = $this->validated($setting->setting, $value, $party);
        $setting->save();

        return $setting;
    }

    public function delete(Party $party, Mod|string $mod, string $code): void
    {
        $setting = $this->find($party, $mod, $code);
        if ($setting === null || ! $setting->exists) {
            return;
        }
        $setting->delete();
        $this->forget($party);
    }

    public function forget(Party $party): void
    {
        unset($this->loaded[$party->id]);
    }

    /**
     * @return array<string, PartyModSetting>
     */
    protected function load(Party $party): array
    {
        if (isset($this->loaded[$party->id])) {
            return $this->loaded[$party->id];
        }

        $rows = ModSetting::query()
            ->join('mods', 'mods.id', '=', 'mod_settings.mod_id')
            ->leftJoin('party_mod_settings', function ($join) use ($party): void {
                $join->on('party_mod_settings.mod_setting_id', '=', 'mod_settings.id')
                    ->where('party_mod_settings.party_id', '=', $party->id);
            })
            ->orderBy('mod_settings.mod_id')
            ->orderBy('mod_settings.order')
            ->select([
                'mod_settings.*',
                'mods.code as mod_code',
                'party_mod_settings.id as pms_id',
                'party_mod_settings.value as pms_value',
                'party_mod_settings.created_at as pms_created_at',
                'party_mod_settings.updated_at as pms_updated_at',
            ])
            ->toBase()
            ->get();

        $settings = [];
        foreach ($rows as $row) {
            $attributes = (array) $row;
            $definition = (new ModSetting)->newFromBuilder(
                array_filter($attributes, fn (string $name): bool => ! str_starts_with($name, 'pms_') && $name !== 'mod_code', ARRAY_FILTER_USE_KEY)
            );

            if ($row->pms_id !== null) {
                $setting = (new PartyModSetting)->newFromBuilder([
                    'id' => $row->pms_id,
                    'party_id' => $party->id,
                    'mod_setting_id' => $definition->id,
                    'value' => $row->pms_value,
                    'created_at' => $row->pms_created_at,
                    'updated_at' => $row->pms_updated_at,
                ]);
            } else {
                $setting = new PartyModSetting;
                $setting->party_id = $party->id;
                $setting->mod_setting_id = $definition->id;
                $setting->setRelation('setting', $definition);
                $setting->value = $definition->default;
            }
            $setting->setRelation('setting', $definition);
            $setting->setRelation('party', $party);
            $settings[$this->key($row->mod_code, $definition->code)] = $setting;
        }

        return $this->loaded[$party->id] = $settings;
    }

    protected function key(Mod|string $mod, string $code): string
    {
        return ($mod instanceof Mod ? $mod->code : $mod).":{$code}";
    }
}
