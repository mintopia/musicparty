<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

readonly class RevokePlayerToken
{
    public function __construct(
        private AuthorisesHost $authorisesHost,
        private RecordPartyLogEntry $record,
    ) {}

    /**
     * @throws NotHostException
     * @throws ModelNotFoundException<PersonalAccessToken>
     */
    public function __invoke(User $actor, Party $party, int $tokenId): void
    {
        DB::transaction(function () use ($actor, $party, $tokenId): void {
            ($this->authorisesHost)($actor, $party);

            $token = $party->tokens()->whereKey($tokenId)->firstOrFail();
            $token->delete();

            ($this->record)($party, 'player_token.revoked', $actor, $token->name, ['token_id' => $tokenId]);
        });
    }
}
