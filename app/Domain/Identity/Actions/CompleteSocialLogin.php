<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\LoginRefusedException;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;

class CompleteSocialLogin
{
    public function __construct(private readonly ResolveSocialUser $resolveSocialUser) {}

    /**
     * @throws LoginRefusedException
     */
    public function __invoke(SocialProvider $provider): User
    {
        if (! $provider->isAvailableForLogin()) {
            throw LoginRefusedException::providerUnavailable($provider->code);
        }

        $remote = $provider->getProvider(route('login.return', $provider->code))->driver()->user();

        return ($this->resolveSocialUser)($provider, $remote);
    }
}
