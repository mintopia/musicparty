<?php

namespace App\Domain\Playback;

use App\Domain\Party\Models\Party;
use App\Domain\Playback\Contracts\BindsToParty;
use App\Domain\Playback\Contracts\Player;

class PartyPlayers
{
    /** @var array<int, Player> */
    private array $players = [];

    public function __construct(private readonly PlayerFactory $factory) {}

    public function for(Party $party): ?Player
    {
        if ($party->player_kind === null || $party->player_kind === '') {
            return null;
        }

        return $this->players[$party->id] ??= $this->register($party, $this->factory->make($party->player_kind));
    }

    public function register(Party $party, Player $player): Player
    {
        if ($player instanceof BindsToParty) {
            $player->forParty($party);
        }

        return $this->players[$party->id] = $player;
    }

    public function forget(Party $party): void
    {
        unset($this->players[$party->id]);
    }
}
