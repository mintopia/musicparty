<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Actions\RecordPartyLogEntry;
use App\Http\Middleware\EnsurePlayerToken;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\NewAccessToken;

readonly class IssuePlayerToken
{
    public function __construct(
        private AuthorisesHost $authorisesHost,
        private RecordPartyLogEntry $record,
    ) {}

    /**
     * @throws NotHostException
     */
    public function __invoke(User $actor, Party $party, string $name): NewAccessToken
    {
        ($this->authorisesHost)($actor, $party);

        return DB::transaction(function () use ($actor, $party, $name): NewAccessToken {
            $token = $party->createToken($name, [EnsurePlayerToken::ABILITY]);

            ($this->record)($party, 'player_token.issued', $actor, $name, ['token_id' => $token->accessToken->getKey()]);

            return $token;
        });
    }
}
