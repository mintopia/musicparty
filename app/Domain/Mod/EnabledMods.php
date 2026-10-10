<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Data\EnabledMod;
use App\Models\Party;

readonly class EnabledMods
{
    public function __construct(private ModRegistry $registry, private PartyMods $partyMods) {}

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
