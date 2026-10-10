<?php

namespace App\Domain\Playback;

use App\Domain\Party\PairingCatalogue;
use App\Domain\Playback\Contracts\Player;
use App\Domain\Playback\Data\PlaybackState;
use App\Domain\Playback\Players\PollingPlayer;
use App\Domain\Playback\Testing\FakePlayer;
use App\Models\Party;

class PartyPlayers
{
    /** @var array<int, Player> */
    private array $players = [];

    public function __construct(private readonly PairingCatalogue $catalogue) {}

    public function for(Party $party): ?Player
    {
        if ($party->player_kind === null || $party->player_kind === '') {
            return null;
        }

        return $this->players[$party->id] ??= $this->register($party, $this->catalogue->player($party->player_kind));
    }

    public function register(Party $party, Player $player): Player
    {
        if ($player instanceof PollingPlayer) {
            $player->forParty($party);
        }

        if ($player instanceof FakePlayer) {
            $code = $party->code;
            $player->onTrackChanged(function (PlaybackState $state) use ($code): void {
                $track = $state->currentTrack;
                $party = Party::findByCode($code);

                if ($track !== null && $party !== null) {
                    app(PlaybackCoordinator::class)->trackChanged($party, $track->providerTrackId);
                }
            });

            $player->onStateChanged(function (PlaybackState $state) use ($code): void {
                $party = Party::findByCode($code);

                if ($state->status === PlaybackStatus::Stopped && $party !== null) {
                    app(PlaybackCoordinator::class)->playbackEnded($party);
                }
            });
        }

        return $this->players[$party->id] = $player;
    }

    public function forget(Party $party): void
    {
        unset($this->players[$party->id]);
    }
}
