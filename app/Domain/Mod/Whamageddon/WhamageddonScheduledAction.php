<?php

namespace App\Domain\Mod\Whamageddon;

use App\Domain\Mod\Contracts\ScheduledAction;
use App\Domain\Mod\Data\ModContext;
use App\Domain\Mod\Data\SystemRequestSpec;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Queue\Actions\SetRequestNotBefore;
use App\Domain\Queue\Jobs\BroadcastPartyQueue;
use App\Domain\Queue\Models\TrackRequest;
use App\Domain\Queue\RequestStatus;
use Carbon\CarbonImmutable;

class WhamageddonScheduledAction implements ScheduledAction
{
    public function __construct(
        private readonly WhamageddonStateTracker $tracker,
        private readonly SetRequestNotBefore $setNotBefore,
        private readonly RecordPartyLogEntry $record,
    ) {}

    public function everySeconds(): int
    {
        return 3600;
    }

    public function run(ModContext $context): array
    {
        $settings = new WhamageddonSettings($context->settings);
        $state = $this->tracker->stateFor($context->party);
        $request = $state->request_id === null ? null : TrackRequest::query()->find($state->request_id);
        $spec = new SystemRequestSpec($settings->trackId());

        if ($request === null || in_array($request->status, [RequestStatus::Removed, RequestStatus::Rejected], true)) {
            return [$spec];
        }

        if ($request->status === RequestStatus::Played) {
            return $this->readdIfDue($settings, $context, $request) ? [$spec] : [];
        }

        if ($request->status === RequestStatus::Queued) {
            $this->tickQueued($settings, $context, $request);
        }

        return [];
    }

    private function readdIfDue(WhamageddonSettings $settings, ModContext $context, TrackRequest $request): bool
    {
        $state = $this->tracker->stateFor($context->party);
        $play = $request->play;
        $anchor = $request->updated_at;

        if ($play !== null) {
            $anchor = $play->played_at;
        }

        $playedAt = $state->last_played_at ?? CarbonImmutable::instance($anchor);

        if ($state->last_played_at === null) {
            $state->forceFill(['last_played_at' => $playedAt])->save();
        }

        if ($settings->now()->lessThan($playedAt->addHours($settings->readdIntervalHours()))) {
            return false;
        }

        $state->forceFill(['accumulated_bonus' => 0, 'last_played_at' => null])->save();

        return true;
    }

    private function tickQueued(WhamageddonSettings $settings, ModContext $context, TrackRequest $request): void
    {
        $now = $settings->now();
        $bonus = $settings->hourlyBonus();

        if ($bonus > 0 && ! $settings->isQuiet($now)) {
            $state = $this->tracker->stateFor($context->party);
            $state->forceFill(['accumulated_bonus' => $state->accumulated_bonus + $bonus])->save();

            ($this->record)($context->party, 'mod.whamageddon_bonus_added', subject: $request->title, details: [
                'mod' => 'Whamageddon',
                'mod_id' => WhamageddonMod::ID,
                'request_id' => $request->id,
                'bonus' => $bonus,
                'accumulated_bonus' => $state->accumulated_bonus,
            ], systemActor: 'mod:'.WhamageddonMod::ID);

            BroadcastPartyQueue::dispatch($context->party->code);
        }

        ($this->setNotBefore)($context->party, $request, $settings->holdUntil($now), 'mod:'.WhamageddonMod::ID);
    }
}
