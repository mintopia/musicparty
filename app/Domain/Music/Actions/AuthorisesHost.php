<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Exceptions\NotHostException;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\Gate;

class AuthorisesHost
{
    /**
     * @throws NotHostException
     */
    public function __invoke(User $user, Party $party): void
    {
        if (Gate::forUser($user)->denies('host', $party)) {
            throw new NotHostException;
        }
    }

    public function linkedAccountFor(User $host, string $providerCode): ?LinkedAccount
    {
        return LinkedAccount::query()
            ->where('user_id', $host->id)
            ->whereHas('provider', fn ($query) => $query->where('code', $providerCode))
            ->first();
    }

    public function hostAccountIdFor(Party $party): string
    {
        $host = $party->user;

        return $host instanceof User ? (string) $this->linkedAccountFor($host, $party->music_provider)?->id : '';
    }
}
