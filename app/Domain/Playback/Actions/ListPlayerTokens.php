<?php

namespace App\Domain\Playback\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Music\Actions\AuthorisesHost;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

readonly class ListPlayerTokens
{
    public function __construct(private AuthorisesHost $authorisesHost) {}

    /**
     * @return Collection<int, PersonalAccessToken>
     *
     * @throws NotHostException
     */
    public function __invoke(User $actor, Party $party): Collection
    {
        ($this->authorisesHost)($actor, $party);

        /** @var Collection<int, PersonalAccessToken> */
        return $party->tokens()->oldest('id')->get(['id', 'name', 'abilities', 'created_at', 'last_used_at']);
    }
}
