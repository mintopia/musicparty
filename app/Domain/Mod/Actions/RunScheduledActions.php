<?php

namespace App\Domain\Mod\Actions;

use App\Domain\Mod\Data\EnabledMod;
use App\Domain\Mod\Data\SystemRequestSpec;
use App\Domain\Mod\EnabledMods;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\PartyState;
use App\Domain\Queue\Actions\RequestTrack;
use App\Domain\Queue\Events\RequestCreated;
use App\Domain\Queue\Exceptions\RequestRefusedException;
use App\Jobs\BroadcastPartyQueue;
use App\Jobs\StartPlayback;
use App\Models\Party;
use Illuminate\Support\Facades\Cache;
use Throwable;

readonly class RunScheduledActions
{
    public function __construct(
        private EnabledMods $mods,
        private RequestTrack $requestTrack,
        private RecordPartyLogEntry $record,
    ) {}

    /**
     * Runs every due Scheduled action of the Mods enabled for a Live Party. Returns how many system Requests were created.
     */
    public function __invoke(Party $party): int
    {
        if ($party->state !== PartyState::Live) {
            return 0;
        }

        $created = 0;

        foreach ($this->mods->for($party) as $enabled) {
            foreach ($enabled->mod->scheduledActions() as $index => $action) {
                if (! $this->claimIfDue($party, $enabled, $index, $action->everySeconds())) {
                    continue;
                }

                try {
                    $specs = $action->run($enabled->context);
                } catch (Throwable $exception) {
                    report($exception);
                    $this->logFailure($party, $enabled, $exception->getMessage());

                    continue;
                }

                foreach ($specs as $spec) {
                    $created += $this->place($party, $enabled, $spec) ? 1 : 0;
                }
            }
        }

        return $created;
    }

    private function claimIfDue(Party $party, EnabledMod $enabled, int $index, int $everySeconds): bool
    {
        $key = "mods:last-run:{$party->id}:{$enabled->mod->id()}:{$index}";
        $now = now()->getTimestamp();
        $last = Cache::get($key);

        if (is_int($last) && $now - $last < $everySeconds) {
            return false;
        }

        Cache::put($key, $now);

        return true;
    }

    private function place(Party $party, EnabledMod $enabled, SystemRequestSpec $spec): bool
    {
        try {
            $request = $this->requestTrack->placeSystemRequest($party, $enabled, $spec);
        } catch (RequestRefusedException $refusal) {
            $this->logFailure($party, $enabled, $refusal->getMessage(), $spec->providerTrackId, 'mod.system_request_refused');

            return false;
        } catch (Throwable $exception) {
            report($exception);
            $this->logFailure($party, $enabled, $exception->getMessage(), $spec->providerTrackId);

            return false;
        }

        if ($request === null) {
            return false;
        }

        ($this->record)($party, 'mod.system_request_created', subject: $request->title, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'request_id' => $request->id,
            'track' => $spec->providerTrackId,
            'bypassed_rules' => $spec->bypassRules,
        ], systemActor: $enabled->systemActor());

        RequestCreated::dispatch($party, $request);
        BroadcastPartyQueue::dispatch($party->code);
        StartPlayback::dispatch($party->code);

        return true;
    }

    private function logFailure(Party $party, EnabledMod $enabled, string $error, ?string $track = null, string $action = 'mod.scheduled_action_failed'): void
    {
        ($this->record)($party, $action, details: [
            'mod' => $enabled->mod->name(),
            'mod_id' => $enabled->mod->id(),
            'track' => $track,
            'error' => $error,
        ], systemActor: $enabled->systemActor());
    }
}
