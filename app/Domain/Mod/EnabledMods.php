<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Party\Models\Party;

readonly class EnabledMods
{
    public function __construct(private ModRegistry $registry, private PartyMods $partyMods) {}

    /**
     * @return list<string>
     */
    public function idsFor(Party $party): array
    {
        return array_map(fn (EnabledMod $enabled): string => $enabled->mod->id(), $this->for($party));
    }

    /**
     * @return list<EnabledMod>
     */
    public function for(Party $party): array
    {
        $enabled = [];

        foreach ($this->partyMods->enabledFor($party) as $context) {
            $mod = $this->registry->find($context->modId);

            if ($mod !== null) {
                $enabled[] = new EnabledMod($mod, $context);
            }
        }

        return $enabled;
    }
}
