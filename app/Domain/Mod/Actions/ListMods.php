<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Data\ModStatus;
use App\Domain\Mod\ModRegistry;
use App\Domain\Mod\ModSettings;
use App\Domain\Party\Models\Party;
use App\Models\PartyMod;

readonly class ListMods
{
    public function __construct(private ModRegistry $registry, private ModSettings $settings) {}

    /**
     * @return list<ModStatus>
     */
    public function __invoke(Party $party): array
    {
        $rows = PartyMod::query()->whereBelongsTo($party)->get()->keyBy('mod_id');
        $statuses = [];

        foreach ($this->registry->all() as $id => $mod) {
            $row = $rows->get($id);
            $plain = $this->settings->resolve($mod, $row?->settings);

            $statuses[] = new ModStatus($mod, $row !== null && $row->enabled, $mod->settings(), $this->settings->mask($mod, $plain));
        }

        return $statuses;
    }
}
