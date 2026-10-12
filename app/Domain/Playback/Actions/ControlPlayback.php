<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Control;
use App\Domain\Playback\EnqueueBackoff;
use App\Domain\Playback\Exceptions\PlaybackControlRefusedException;
use App\Domain\Playback\Exceptions\PlayerCommandRejectedException;
use App\Domain\Playback\Exceptions\PlayerDisconnectedException;
use App\Domain\Playback\Exceptions\PlayerRateLimitedException;
use App\Domain\Playback\Exceptions\UnsupportedControl;
use App\Domain\Playback\PartyPlayers;
use App\Domain\Playback\PlaybackStatus;
use App\Domain\Queue\Models\Play;
use App\Domain\Queue\ReplayMarker;

class ControlPlayback
{
    private const int RESTART_THRESHOLD_MS = 3000;

    public function __construct(private readonly PartyPlayers $players, private readonly EnqueueBackoff $backoff, private readonly ReplayMarker $replayMarker) {}

    /**
     * @throws PlaybackControlRefusedException
     */
    public function __invoke(Party $party, Control $control, ?int $value = null): void
    {
        $player = $this->players->for($party) ?? throw PlaybackControlRefusedException::noPlayer();

        if (! $player->supports($control)) {
            throw PlaybackControlRefusedException::unsupported($control);
        }

        if (in_array($control, [Control::Play, Control::Skip, Control::Previous], true)) {
            $this->backoff->clearForParty($party);
        }

        try {
            $this->send($player, $control, $value ?? 0, $party);
        } catch (UnsupportedControl) {
            throw PlaybackControlRefusedException::unsupported($control);
        } catch (PlayerDisconnectedException) {
            throw PlaybackControlRefusedException::disconnected();
        } catch (PlayerRateLimitedException $failure) {
            throw PlaybackControlRefusedException::rateLimited($failure->retryAfterSeconds);
        } catch (PlayerCommandRejectedException $failure) {
            throw PlaybackControlRefusedException::rejected($failure->getMessage());
        }
    }

    private function send(Player $player, Control $control, int $value, Party $party): void
    {
        match ($control) {
            Control::Play => $player->play(),
            Control::Pause => $player->pause(),
            Control::Skip => $player->skip(),
            Control::Previous => $this->previous($player, $party),
            Control::Seek => $player->seek($value),
            Control::Volume => $player->volume($value),
        };
    }

    private function previous(Player $player, Party $party): void
    {
        if ($this->positionMs($player) >= self::RESTART_THRESHOLD_MS) {
            $player->seek(0);

            return;
        }

        $this->markReplay($player, $party);
        $player->previous();
    }

    private function markReplay(Player $player, Party $party): void
    {
        $current = $player->state()->currentTrack?->providerTrackId;

        $last = Play::query()
            ->where('party_id', $party->id)
            ->whereNotNull('track_request_id')
            ->when($current !== null, fn ($query) => $query->where('provider_track_id', '!=', $current))
            ->latest('played_at')
            ->latest('id')
            ->first();

        if ($last !== null) {
            $this->replayMarker->set($party, (int) $last->track_request_id, $last->provider_track_id);
        }
    }

    private function positionMs(Player $player): int
    {
        $state = $player->state();

        $elapsed = $state->status === PlaybackStatus::Playing && $state->updatedAt !== null
            ? max(0, (int) $state->updatedAt->diffInMilliseconds(now(), true))
            : 0;

        return $state->positionMs + $elapsed;
    }
}
