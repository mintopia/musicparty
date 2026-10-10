<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\ModSettings;
use App\Domain\Mod\PartyMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\PartyMod;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class EnableMod
{
    public function __construct(
        private FindMod $findMod,
        private ModSettings $settings,
        private PartyMods $partyMods,
        private RecordPartyLogEntry $record,
    ) {}

    public function __invoke(User $actor, Party $party, string $modId): void
    {
        $mod = ($this->findMod)($modId);

        $changed = false;

        DB::transaction(function () use (&$changed, $actor, $party, $mod): void {
            $row = PartyMod::query()->whereBelongsTo($party)->where('mod_id', $mod->id())->first()
                ?? new PartyMod()->forceFill(['party_id' => $party->id, 'mod_id' => $mod->id()]);

            if ($row->exists && $row->enabled) {
                return;
            }

            $row->settings ??= $this->settings->encrypt($mod, $this->settings->defaults($mod));
            $row->enabled = true;
            $row->save();

            $changed = true;

            ($this->record)($party, 'mod.enabled', $actor, $mod->id());
        });

        $this->partyMods->forget($party);

        if ($changed) {
            BroadcastPartyQueue::dispatch($party->code);
        }
    }
}
