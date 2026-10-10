<?php

namespace App\Domain\Party\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Membership\Actions\AddHostMember;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PairingCatalogue;
use App\Domain\Party\PartyState;
use App\Domain\Playback\Actions\PairPlayer;
use App\Domain\Playback\Exceptions\IncompatibleProviderException;
use Illuminate\Support\Facades\DB;

readonly class CreateParty
{
    public function __construct(
        private PairingCatalogue $catalogue,
        private PairPlayer $pairPlayer,
        private GeneratePartyCode $generateCode,
        private RecordPartyLogEntry $record,
        private AddHostMember $addHost,
    ) {}

    /**
     * @throws IncompatibleProviderException
     */
    public function __invoke(User $host, string $name, string $musicProvider, string $playerKind): Party
    {
        ($this->pairPlayer)($this->catalogue->player($playerKind), $this->catalogue->provider($musicProvider));

        return DB::transaction(function () use ($host, $name, $musicProvider, $playerKind): Party {
            $party = Party::query()->forceCreate([
                'code' => ($this->generateCode)(),
                'name' => $name,
                'user_id' => $host->id,
                'music_provider' => $musicProvider,
                'player_kind' => $playerKind,
                'state' => PartyState::Paused,
            ]);

            ($this->addHost)($party, $host);

            ($this->record)($party, 'party.created', $host, details: [
                'name' => $name,
                'music_provider' => $musicProvider,
                'player_kind' => $playerKind,
            ]);

            return $party->fresh() ?? $party;
        });
    }
}
