<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\LoginRefusedException;
use App\Models\SocialProvider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StartSocialLogin
{
    /**
     * @throws LoginRefusedException
     */
    public function __invoke(SocialProvider $provider): RedirectResponse
    {
        if (! $provider->isAvailableForLogin()) {
            throw LoginRefusedException::providerUnavailable($provider->code);
        }

        return $provider->getProvider(route('login.return', $provider->code))->driver()->redirect();
    }
}
