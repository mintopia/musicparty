<?php

namespace App\Services;

use App\Models\Mod;
use App\Models\ModSetting;
use App\Models\Party;
use App\Models\PartyModSetting;
use InvalidArgumentException;

class ModSettingsStore
{
    /** @var array<int, array<string, PartyModSetting>> */
    protected array $loaded = [];

    public function value(Party $party, Mod|string $mod, string $code, mixed $default = null): mixed
    {
        $setting = $this->find($party, $mod, $code);

        return $setting->value ?? $default;
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
        $setting->value = $value;
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
