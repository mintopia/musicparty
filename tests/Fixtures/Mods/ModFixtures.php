<?php

namespace Tests\Fixtures\Mods;

use App\Domain\Mod\Contracts\Mod;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\PartyMods;
use App\Domain\Party\Models\Party;

class ModFixtures
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public static function enable(Party $party, Mod $mod, array $settings = []): Mod
    {
        $registry = app(ModRegistry::class);

        if ($registry->find($mod->id()) === null) {
            $registry->register($mod);
        }

        PartyMod::factory()->for($party)->forMod($mod->id())->create(['settings' => $settings]);
        app(PartyMods::class)->forget($party);

        return $mod;
    }

    public static function disable(Party $party, Mod $mod): void
    {
        PartyMod::query()->where('party_id', $party->id)->where('mod_id', $mod->id())->update(['enabled' => false]);
        app(PartyMods::class)->forget($party);
    }
}
