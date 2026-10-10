<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Party\Models\Party;

class PartyMods
{
    /** @var array<int, array<string, PartyMod>> */
    private array $rows = [];

    public function __construct(private readonly ModRegistry $registry, private readonly ModSettings $settings) {}

    /**
     * @return list<ModContext>
     */
    public function enabledFor(Party $party): array
    {
        $contexts = [];

        foreach ($this->rowsFor($party) as $modId => $row) {
            $context = $this->contextFromRow($party, $modId, $row);

            if ($context !== null) {
                $contexts[] = $context;
            }
        }

        return $contexts;
    }

    public function contextFor(Party $party, string $modId): ?ModContext
    {
        $row = $this->rowsFor($party)[$modId] ?? null;

        return $row === null ? null : $this->contextFromRow($party, $modId, $row);
    }

    public function forget(Party $party): void
    {
        unset($this->rows[$party->id]);
    }

    /**
     * @return array<string, PartyMod>
     */
    private function rowsFor(Party $party): array
    {
        return $this->rows[$party->id] ??= PartyMod::query()
            ->whereBelongsTo($party)
            ->where('enabled', true)
            ->orderBy('id')
            ->get()
            ->keyBy('mod_id')
            ->all();
    }

    private function contextFromRow(Party $party, string $modId, PartyMod $row): ?ModContext
    {
        $mod = $this->registry->find($modId);

        if ($mod === null) {
            return null;
        }

        return new ModContext($modId, $party, $this->settings->resolve($mod, $row->settings));
    }
}
