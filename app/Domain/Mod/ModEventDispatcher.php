<?php

namespace App\Domain\Mod;

use App\Domain\Mod\Contracts\PartyScopedEvent;
use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Models\Party;
use Throwable;

readonly class ModEventDispatcher
{
    public function __construct(private EnabledMods $mods, private RecordPartyLogEntry $record) {}

    /**
     * Delivers the event to the listeners of Mods enabled for its Party; a failing listener is logged and never rethrown.
     */
    public function handle(PartyScopedEvent $event): void
    {
        $party = $event->party();

        try {
            $enabled = $this->mods->for($party);
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($enabled as $mod) {
            foreach ($mod->mod->listeners() as $eventClass => $listener) {
                if (! $event instanceof $eventClass) {
                    continue;
                }

                try {
                    $listener($event, $mod->context);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->logFailure($party, $mod, $event, $exception);
                }
            }
        }
    }

    private function logFailure(Party $party, EnabledMod $mod, object $event, Throwable $exception): void
    {
        try {
            ($this->record)($party, 'mod.listener_failed', subject: $mod->mod->name(), details: [
                'mod_id' => $mod->mod->id(),
                'event' => $event::class,
                'error' => $exception->getMessage(),
            ], systemActor: $mod->systemActor());
        } catch (Throwable $logFailure) {
            report($logFailure);
        }
    }
}
