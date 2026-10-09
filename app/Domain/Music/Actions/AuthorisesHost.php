<?php

namespace App\Domain\Music\Actions;

use App\Domain\Music\Exceptions\NotHostException;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\User;

class AuthorisesHost
{
    /**
     * @throws NotHostException
     */
    public function __invoke(User $user, Party $party): void
    {
        if ($user->id !== $party->user_id) {
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
}
