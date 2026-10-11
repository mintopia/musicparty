<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\AccountLinkRefusedException;
use App\Domain\Identity\Models\SocialProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StartAccountLink
{
    /**
     * @throws AccountLinkRefusedException
     */
    public function __invoke(SocialProvider $provider): RedirectResponse
    {
        if (! $provider->isAvailableForLinking()) {
            throw AccountLinkRefusedException::providerUnavailable($provider->name);
        }

        return $provider->getProvider(route('profile.accounts.return', $provider->code))->driver()->redirect();
    }
}
