<?php

namespace App\Mods;

use App\Models\Mod as ModModel;
use App\Models\Party;

class PartyMods
{
    public function __construct(protected ModRegistry $registry) {}

    public function isEnabled(Party $party, string $code): bool
    {
        return $party->mods()->where('mods.code', $code)->whereNull('mods.removed_at')->exists();
    }

    public function enable(Party $party, string $code): void
    {
        $manifest = $this->registry->find($code);
        $mod = ModModel::whereCode($code)->whereNull('removed_at')->first();
        if ($manifest === null || $mod === null || $this->isEnabled($party, $code)) {
            return;
        }
        $party->mods()->attach($mod->id);
        $manifest->onEnable($party);
    }

    public function disable(Party $party, string $code): void
    {
        $manifest = $this->registry->find($code);
        $mod = ModModel::whereCode($code)->first();
        if ($mod === null || ! $party->mods()->whereKey($mod->id)->exists()) {
            return;
        }
        $party->mods()->detach($mod->id);
        $manifest?->onDisable($party);
    }

    /**
     * @param  list<string>  $codes
     */
    public function sync(Party $party, array $codes): void
    {
        foreach (array_keys($this->registry->all()) as $code) {
            if (in_array($code, $codes, true)) {
                $this->enable($party, $code);
            } else {
                $this->disable($party, $code);
            }
        }
    }
}
