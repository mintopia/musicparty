<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use App\Models\TrackRequest;
use Throwable;

readonly class ApplyScoreModifiers
{
    public function __construct(private EnabledMods $mods, private RecordPartyLogEntry $record) {}

    /**
     * Per-Mod adjustments for each Request, keyed by Request id then Mod id; zero adjustments are omitted.
     * A modifier that throws contributes nothing and is recorded in the Party Log.
     *
     * @param  iterable<TrackRequest>  $requests
     * @return array<int, array<string, array{name: string, value: int}>>
     */
    public function __invoke(Party $party, iterable $requests): array
    {
        $adjustments = [];

        foreach ($this->mods->for($party) as $enabled) {
            foreach ($enabled->mod->scoreModifiers() as $modifier) {
                foreach ($requests as $request) {
                    try {
                        $value = $modifier->adjustment($enabled->context, $request);
                    } catch (Throwable $exception) {
                        report($exception);
                        $this->logFailure($party, $enabled, $request, $exception);

                        continue;
                    }

                    if ($value !== 0) {
                        $current = $adjustments[$request->id][$enabled->mod->id()]['value'] ?? 0;
                        $adjustments[$request->id][$enabled->mod->id()] = ['name' => $enabled->mod->name(), 'value' => $current + $value];
                    }
                }
            }
        }

        return $adjustments;
    }

    private function logFailure(Party $party, EnabledMod $enabled, TrackRequest $request, Throwable $exception): void
    {
        ($this->record)($party, 'mod.score_failed', subject: $request->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'request_id' => $request->id,
            'error' => $exception->getMessage(),
        ], systemActor: $enabled->systemActor());
    }
}
