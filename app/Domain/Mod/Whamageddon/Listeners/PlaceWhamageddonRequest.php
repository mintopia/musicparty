<?php

namespace App\Domain\Mod\Whamageddon\Listeners;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\Data\SystemRequestSpec;
use App\Domain\Mod\EnabledMods;
use App\Domain\Mod\Events\ModEnabled;
use App\Domain\Mod\Whamageddon\WhamageddonMod;
use App\Domain\Mod\Whamageddon\WhamageddonSettings;
use App\Domain\Mod\Whamageddon\WhamageddonStateTracker;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Playback\Jobs\StartPlayback;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use Throwable;

readonly class PlaceWhamageddonRequest
{
    public function __construct(
        private EnabledMods $mods,
        private RequestTrack $requestTrack,
        private WhamageddonStateTracker $tracker,
        private RecordPartyLogEntry $record,
    ) {}

    public function handle(ModEnabled $event): void
    {
        if ($event->modId !== WhamageddonMod::ID) {
            return;
        }

        $enabled = collect($this->mods->for($event->party))->first(fn (EnabledMod $candidate): bool => $candidate->mod->id() === WhamageddonMod::ID);

        if ($enabled === null) {
            return;
        }

        $party = $event->party;
        $trackId = (new WhamageddonSettings($enabled->context->settings))->trackId();

        try {
            $request = $this->requestTrack->placeSystemRequest($party, $enabled, new SystemRequestSpec($trackId));
        } catch (RequestRefusedException $refusal) {
            $this->logFailure($enabled, $trackId, $refusal->getMessage(), 'mod.system_request_refused');

            return;
        } catch (Throwable $exception) {
            report($exception);
            $this->logFailure($enabled, $trackId, $exception->getMessage(), 'mod.scheduled_action_failed');

            return;
        }

        if ($request === null) {
            return;
        }

        $this->tracker->adopt($party, $request);

        ($this->record)($party, 'mod.system_request_created', subject: $request->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'request_id' => $request->id,
            'track' => $trackId,
            'bypassed_rules' => false,
        ], systemActor: $enabled->systemActor());

        RequestCreated::dispatch($party, $request);
        BroadcastPartyQueue::dispatch($party->code);
        StartPlayback::dispatch($party->code);
    }

    private function logFailure(EnabledMod $enabled, string $trackId, string $error, string $action): void
    {
        ($this->record)($enabled->context->party, $action, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'track' => $trackId,
            'error' => $error,
        ], systemActor: $enabled->systemActor());
    }
}
