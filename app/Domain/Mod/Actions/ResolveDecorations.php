<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Data\Decoration;
use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\Models\TrackRequest;
use Throwable;

class ResolveDecorations
{
    /** @var array<int, Party> */
    private array $parties = [];

    public function __construct(private readonly EnabledMods $mods, private readonly RecordPartyLogEntry $record) {}

    /**
     * Valid Decorations from the Mods enabled for the subject's Party, as serialisable arrays.
     * A provider that throws contributes nothing and is recorded in the Party Log; invalid values are discarded.
     *
     * @return list<array{mod_id: string, badge: string|null, label: string|null, icon: string|null, accent: string, variant: string}>
     */
    public function __invoke(TrackRequest|Play $subject): array
    {
        $party = $this->partyFor($subject);
        $decorations = [];

        foreach ($this->mods->for($party) as $enabled) {
            foreach ($enabled->mod->decorationProviders() as $provider) {
                try {
                    $produced = $provider->decorate($enabled->context, $subject);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->logFailure($party, $enabled, $subject, $exception);

                    continue;
                }

                foreach ($produced as $item) {
                    $decoration = $item instanceof Decoration
                        ? Decoration::tryFromArray($enabled->mod->id(), $item->toArray())
                        : Decoration::tryFromArray($enabled->mod->id(), $item);

                    if ($decoration !== null) {
                        $decorations[] = $decoration->toArray();
                    }
                }
            }
        }

        return $decorations;
    }

    private function partyFor(TrackRequest|Play $subject): Party
    {
        return $this->parties[$subject->party_id] ??= Party::query()->findOrFail($subject->party_id);
    }

    private function logFailure(Party $party, EnabledMod $enabled, TrackRequest|Play $subject, Throwable $exception): void
    {
        ($this->record)($party, 'mod.decoration_failed', subject: $subject->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            $subject instanceof Play ? 'play_id' : 'request_id' => $subject->id,
            'error' => $exception->getMessage(),
        ], systemActor: $enabled->systemActor());
    }
}
