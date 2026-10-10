<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Mod\PartyMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use Illuminate\Support\Facades\DB;

readonly class DisableMod
{
    public function __construct(
        private FindMod $findMod,
        private PartyMods $partyMods,
        private RecordPartyLogEntry $record,
    ) {}

    public function __invoke(User $actor, Party $party, string $modId): void
    {
        $mod = ($this->findMod)($modId);

        $changed = false;

        DB::transaction(function () use (&$changed, $actor, $party, $mod): void {
            $row = PartyMod::query()->whereBelongsTo($party)->where('mod_id', $mod->id())->where('enabled', true)->first();

            if ($row === null) {
                return;
            }

            $row->forceFill(['enabled' => false])->save();

            $changed = true;

            ($this->record)($party, 'mod.disabled', $actor, $mod->id());
        });

        $this->partyMods->forget($party);

        if ($changed) {
            BroadcastPartyQueue::dispatch($party->code);
        }
    }
}
