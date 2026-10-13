<?php

namespace App\Services;

use App\Enums\SettingType;
use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Mods\PartyMods;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ModSettingsUpdater
{
    public function __construct(protected ModSettingsStore $store, protected PartyMods $partyMods) {}

    /**
     * Apply the Mods screen payload for a Party: enablement and Host-editable settings.
     *
     * @param  array<string, mixed>  $input  The `mods` request value.
     *
     * @throws ValidationException
     */
    public function updateParty(Party $party, array $input): void
    {
        $mods = Mod::query()->whereNull('removed_at')->get()->keyBy('code');
        $editable = $this->settingsByMod(
            ModSetting::query()->where('private', false)->where('instance_only', false)->whereIn('mod_id', $mods->pluck('id'))->get(),
            $mods->all(),
        );

        $writes = $this->collect($input, $mods->all(), $editable, $party, true);
        $enabled = $party->mods()->pluck('mods.code')->all();
        foreach ($input as $code => $entry) {
            if (is_array($entry) && array_key_exists('enabled', $entry)) {
                $enabled = array_values(array_diff($enabled, [(string) $code]));
                if (filter_var($entry['enabled'], FILTER_VALIDATE_BOOLEAN)) {
                    $enabled[] = (string) $code;
                }
            }
        }

        DB::transaction(function () use ($writes, $party): void {
            foreach ($writes as [$modCode, $definition, $value]) {
                $this->store->set($party, $modCode, $definition->code, $value);
            }
        });
        $this->partyMods->sync($party, $enabled);
    }

    /**
     * Apply the admin Mods screen payload: instance-only settings.
     *
     * @param  array<string, mixed>  $input  The `mods` request value.
     *
     * @throws ValidationException
     */
    public function updateInstance(array $input): void
    {
        $mods = Mod::query()->whereNull('removed_at')->get()->keyBy('code');
        $editable = $this->settingsByMod(
            ModSetting::query()->where('private', false)->where('instance_only', true)->whereIn('mod_id', $mods->pluck('id'))->get(),
            $mods->all(),
        );

        $writes = $this->collect($input, $mods->all(), $editable, null, false);

        DB::transaction(function () use ($writes): void {
            foreach ($writes as [$modCode, $definition, $value]) {
                $this->store->setInstance($modCode, $definition->code, $value);
            }
        });
    }

    /**
     * @param  iterable<ModSetting>  $settings
     * @param  array<string, Mod>  $mods
     * @return array<string, array<string, ModSetting>>
     */
    protected function settingsByMod(iterable $settings, array $mods): array
    {
        $byId = [];
        foreach ($mods as $mod) {
            $byId[$mod->id] = $mod->code;
        }
        $result = [];
        foreach ($settings as $setting) {
            $result[$byId[$setting->mod_id]][$setting->code] = $setting;
        }

        return $result;
    }

    /**
     * Validate every submitted setting before anything is written.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, Mod>  $mods
     * @param  array<string, array<string, ModSetting>>  $editable
     * @return list<array{0: string, 1: ModSetting, 2: mixed}>
     *
     * @throws ValidationException
     */
    protected function collect(array $input, array $mods, array $editable, ?Party $party, bool $allowEnabled): array
    {
        $errors = [];
        $writes = [];
        foreach ($input as $modCode => $entry) {
            $modCode = (string) $modCode;
            if (! isset($mods[$modCode]) || ! is_array($entry)) {
                $errors["mods.{$modCode}"] = ['Unknown mod.'];

                continue;
            }
            if (array_key_exists('enabled', $entry) && ! $allowEnabled) {
                $errors["mods.{$modCode}.enabled"] = ['Enablement is not editable here.'];
            }
            $settings = $entry['settings'] ?? [];
            if (! is_array($settings)) {
                $errors["mods.{$modCode}.settings"] = ['Invalid settings.'];

                continue;
            }
            foreach ($settings as $code => $value) {
                $code = (string) $code;
                $definition = $editable[$modCode][$code] ?? null;
                if ($definition === null) {
                    $errors["mods.{$modCode}.settings.{$code}"] = ['This setting is not editable.'];

                    continue;
                }
                try {
                    $this->store->validated($definition, $value, $party);
                } catch (ValidationException $e) {
                    if ($this->keepsStoredSecret($definition, $value, $party)) {
                        continue;
                    }
                    $errors["mods.{$modCode}.settings.{$code}"] = $e->errors()[$code] ?? ['Invalid value.'];

                    continue;
                }
                $writes[] = [$modCode, $definition, $value];
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $writes;
    }

    protected function keepsStoredSecret(ModSetting $definition, mixed $value, ?Party $party): bool
    {
        if ($definition->type !== SettingType::stSecret || ($value !== null && $value !== '')) {
            return false;
        }
        if ($party === null) {
            return $definition->getRawOriginal('instance_value') !== null;
        }
        $setting = $this->store->find($party, $definition->mod, $definition->code);

        return $setting !== null && $setting->exists && $setting->getRawOriginal('value') !== null;
    }
}
