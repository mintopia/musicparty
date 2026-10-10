<?php

namespace App\Domain\Playback;

use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Queue\Actions\AdvanceQueue;
use App\Domain\Queue\Actions\SelectUpNext;
use App\Domain\Queue\Actions\TopUpFallbackRequests;
use App\Domain\Queue\RequestStatus;
use App\Jobs\BroadcastPartyQueue;
use App\Models\Party;
use App\Models\TrackRequest;
use Illuminate\Support\Facades\DB;

class PlaybackCoordinator
{
    public function __construct(
        private readonly PartyPlayers $players,
        private readonly TopUpFallbackRequests $topUp,
        private readonly SelectUpNext $select,
        private readonly AdvanceQueue $advanceQueue,
        private readonly RecordPartyLogEntry $record,
    ) {}

    public function startIfIdle(Party $party): void
    {
        $party = $this->liveParty($party);

        if ($party === null || $this->requestWithStatus($party, RequestStatus::Playing) !== null) {
            return;
        }

        $this->selectAndSend($party);
    }

    public function trackChanged(Party $party, string $providerTrackId): void
    {
        $party = $this->liveParty($party);

        if ($party === null) {
            return;
        }

        $result = ($this->advanceQueue)($party, $providerTrackId);

        if ($result->duplicate) {
            return;
        }

        BroadcastPartyQueue::dispatch($party->code);

        if ($result->unexpectedTrack) {
            $this->stopPlayback($party, 'unexpected_track');

            return;
        }

        if ($this->feedMode($party) === FeedMode::Ahead) {
            $this->selectAndSend($party);
        }
    }

    public function playbackEnded(Party $party): void
    {
        $party = $this->liveParty($party);

        if ($party === null) {
            return;
        }

        $result = ($this->advanceQueue)($party, null);

        if ($result->playing === null) {
            BroadcastPartyQueue::dispatch($party->code);
            $this->selectAndSend($party);
        }
    }

    public function tick(Party $party): void
    {
        $party = $this->liveParty($party);

        if ($party === null) {
            return;
        }

        if ($this->requestWithStatus($party, RequestStatus::UpNext) !== null) {
            $this->sendUpNext($party);

            return;
        }

        $playing = $this->requestWithStatus($party, RequestStatus::Playing);

        if ($playing === null || $this->feedMode($party) === FeedMode::Ahead || $this->secondsRemaining($playing) <= $this->leadSeconds()) {
            $this->selectAndSend($party);
        }
    }

    private function selectAndSend(Party $party): void
    {
        ($this->topUp)($party);
        $selected = ($this->select)($party);

        if ($selected !== null) {
            BroadcastPartyQueue::dispatch($party->code);
        }

        if ($this->requestWithStatus($party, RequestStatus::UpNext) !== null) {
            $this->sendUpNext($party);

            return;
        }

        if ($this->requestWithStatus($party, RequestStatus::Playing) === null) {
            $this->stopPlayback($party, 'queue_empty');
        }
    }

    private function sendUpNext(Party $party): void
    {
        $player = $this->players->for($party);

        if ($player === null) {
            return;
        }

        $request = DB::transaction(function () use ($party): ?TrackRequest {
            Party::query()->whereKey($party->id)->lockForUpdate()->firstOrFail();

            $request = TrackRequest::query()
                ->where('party_id', $party->id)
                ->where('status', RequestStatus::UpNext)
                ->whereNull('enqueued_at')
                ->first();

            $request?->forceFill(['enqueued_at' => now()])->save();

            return $request;
        });

        if ($request === null) {
            return;
        }

        try {
            $player->enqueue($party->music_provider, $request->provider_track_id);
        } catch (PlayerDisconnectedException) {
            $request->forceFill(['enqueued_at' => null])->save();
            ($this->record)($party, 'player.enqueue_failed', subject: $request->title, systemActor: 'player');
        }
    }

    private function stopPlayback(Party $party, string $reason): void
    {
        $player = $this->players->for($party);

        if ($player === null || $player->state()->status !== PlaybackStatus::Playing) {
            return;
        }

        try {
            $player->pause();
        } catch (PlayerDisconnectedException|UnsupportedControl) {
            ($this->record)($party, 'player.stop_failed', details: ['reason' => $reason], systemActor: 'player');

            return;
        }

        ($this->record)($party, 'player.stopped', details: ['reason' => $reason], systemActor: 'player');
    }

    private function feedMode(Party $party): FeedMode
    {
        return $this->players->for($party)?->feedMode() ?? FeedMode::Ahead;
    }

    private function secondsRemaining(TrackRequest $playing): int
    {
        $startedAt = $playing->started_at ?? now();

        return $startedAt->addMilliseconds($playing->duration_ms)->getTimestamp() - now()->getTimestamp();
    }

    private function leadSeconds(): int
    {
        return (int) config('musicparty.just_in_time_lead_seconds', 15);
    }

    private function requestWithStatus(Party $party, RequestStatus $status): ?TrackRequest
    {
        return TrackRequest::query()->where('party_id', $party->id)->where('status', $status)->oldest('id')->first();
    }

    private function liveParty(Party $party): ?Party
    {
        $fresh = Party::query()->find($party->id);

        return $fresh !== null && $fresh->state === PartyState::Live && $this->players->for($fresh) instanceof Player ? $fresh : null;
    }
}
