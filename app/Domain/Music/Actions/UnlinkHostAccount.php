<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Actions\DeleteLinkedAccount;
use App\Domain\Identity\Actions\UpdateLinkedAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Party\Actions\PauseParty;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Exceptions\InvalidPartyTransition;
use App\Domain\Party\Models\Party;
use App\Domain\Party\PartyState;

readonly class UnlinkHostAccount
{
    private const string POLLING_KIND = 'polling';

    public function __construct(private AuthorisesHost $authorisesHost, private DeleteLinkedAccount $deleteAccount, private UpdateLinkedAccount $updateAccount, private PauseParty $pauseParty, private RecordPartyLogEntry $record) {}

    public function __invoke(User $user, string $providerCode = 'spotify'): void
    {
        $account = $this->authorisesHost->linkedAccountFor($user, $providerCode);

        if ($account === null) {
            return;
        }

        if ($account->canDelete()) {
            ($this->deleteAccount)($account);
        } else {
            ($this->updateAccount)($account, ['access_token' => null, 'refresh_token' => null, 'access_token_expires_at' => null, 'scopes' => null]);
        }

        if ($providerCode !== 'spotify') {
            return;
        }

        Party::query()
            ->where('user_id', $user->id)
            ->where('state', PartyState::Live)
            ->where('player_kind', self::POLLING_KIND)
            ->get()
            ->each(function (Party $party): void {
                try {
                    ($this->pauseParty)(null, $party, 'player', 'spotify_unlinked');
                } catch (InvalidPartyTransition) {
                    return;
                }

                ($this->record)($party, 'player.host_account_unlinked', systemActor: 'player');
            });
    }
}
