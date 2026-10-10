<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Mod\Models\PartyMod;
use App\Domain\Mod\ModSettings;
use App\Domain\Mod\PartyMods;
use App\Domain\Mod\SettingKind;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class UpdateModSettings
{
    public function __construct(
        private FindMod $findMod,
        private ModSettings $settings,
        private PartyMods $partyMods,
        private RecordPartyLogEntry $record,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $actor, Party $party, string $modId, array $input): void
    {
        $mod = ($this->findMod)($modId);

        $changed = false;

        DB::transaction(function () use (&$changed, $actor, $party, $mod, $input): void {
            $row = PartyMod::query()->whereBelongsTo($party)->where('mod_id', $mod->id())->where('enabled', true)->lockForUpdate()->first();

            if ($row === null) {
                throw ValidationException::withMessages(['mod' => 'Enable the Mod before changing its settings.']);
            }

            $current = $this->settings->resolve($mod, $row->settings);
            $next = $this->settings->validate($mod, $input, $current);

            $details = [];

            foreach ($mod->settings() as $definition) {
                $key = $definition->key;

                if (($current[$key] ?? null) === ($next[$key] ?? null)) {
                    continue;
                }

                $details[$key] = $definition->kind === SettingKind::Secret
                    ? ['changed' => true]
                    : ['old' => $current[$key] ?? null, 'new' => $next[$key] ?? null];
            }

            if ($details === []) {
                return;
            }

            $row->forceFill(['settings' => $this->settings->encrypt($mod, $next)])->save();

            $changed = true;

            ($this->record)($party, 'mod.settings_updated', $actor, $mod->id(), ['changes' => $details]);
        });

        $this->partyMods->forget($party);

        if ($changed) {
            BroadcastPartyQueue::dispatch($party->code);
        }
    }
}
