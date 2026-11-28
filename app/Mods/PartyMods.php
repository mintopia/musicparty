<?php

namespace App\Mods;

use App\Models\Mod as ModModel;
use App\Models\Party;
use Illuminate\Support\Facades\DB;

class PartyMods
{
    /** @var array<int, array<string, true>> */
    protected array $enabled = [];

    /**
     * @param  array<int, int>  $partyIds
     * @return array<int, int>
     */
    public function partyIdsWithEnabled(array $partyIds, string $code): array
    {
        return DB::table('party_mods')
            ->join('mods', 'mods.id', '=', 'party_mods.mod_id')
            ->where('mods.code', $code)
            ->whereNull('mods.removed_at')
            ->whereIn('party_mods.party_id', $partyIds)
            ->pluck('party_mods.party_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function isEnabled(Party $party, string $code): bool
    {
        $this->enabled[$party->id] ??= array_fill_keys(
            $party->mods()->whereNull('mods.removed_at')->pluck('mods.code')->all(),
            true,
        );

        return isset($this->enabled[$party->id][$code]);
    }

    public function forget(Party $party): void
    {
        unset($this->enabled[$party->id]);
    }

    public function enable(Party $party, string $code): void
    {
        $manifest = app(ModRegistry::class)->find($code);
        $mod = ModModel::whereCode($code)->whereNull('removed_at')->first();
        if ($manifest === null || $mod === null || $this->isEnabled($party, $code)) {
            return;
        }
        $party->mods()->attach($mod->id);
        $this->forget($party);
        $manifest->onEnable($party);
    }

    public function disable(Party $party, string $code): void
    {
        $manifest = app(ModRegistry::class)->find($code);
        $mod = ModModel::whereCode($code)->first();
        if ($mod === null || ! $party->mods()->whereKey($mod->id)->exists()) {
            return;
        }
        $party->mods()->detach($mod->id);
        $this->forget($party);
        $manifest?->onDisable($party);
    }

    /**
     * @param  list<string>  $codes
     */
    public function sync(Party $party, array $codes): void
    {
        foreach (array_keys(app(ModRegistry::class)->all()) as $code) {
            if (in_array($code, $codes, true)) {
                $this->enable($party, $code);
            } else {
                $this->disable($party, $code);
            }
        }
    }
}
