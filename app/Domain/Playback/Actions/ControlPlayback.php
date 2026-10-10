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

class ControlPlayback
{
    public function __construct(private readonly PartyPlayers $players, private readonly EnqueueBackoff $backoff) {}

    /**
     * @throws PlaybackControlRefusedException
     */
    public function __invoke(Party $party, Control $control, ?int $value = null): void
    {
        $player = $this->players->for($party) ?? throw PlaybackControlRefusedException::noPlayer();

        if (! $player->supports($control)) {
            throw PlaybackControlRefusedException::unsupported($control);
        }

        if (in_array($control, [Control::Play, Control::Skip], true)) {
            $this->backoff->clearForParty($party);
        }

        try {
            $this->send($player, $control, $value ?? 0);
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

    private function send(Player $player, Control $control, int $value): void
    {
        match ($control) {
            Control::Play => $player->play(),
            Control::Pause => $player->pause(),
            Control::Skip => $player->skip(),
            Control::Seek => $player->seek($value),
            Control::Volume => $player->volume($value),
        };
    }
}
