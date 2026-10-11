<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\AccountLinkRefusedException;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as TwoUser;

class CompleteAccountLink
{
    /**
     * @throws AccountLinkRefusedException
     */
    public function __invoke(User $user, SocialProvider $provider): LinkedAccount
    {
        if (! $provider->isAvailableForLinking()) {
            throw AccountLinkRefusedException::providerUnavailable($provider->name);
        }

        $remote = $provider->getProvider(route('profile.accounts.return', $provider->code))->driver()->user();

        try {
            return $this->link($user, $provider, $remote);
        } catch (UniqueConstraintViolationException) {
            throw AccountLinkRefusedException::ownedByAnotherUser();
        }
    }

    private function link(User $user, SocialProvider $provider, SocialiteUser $remote): LinkedAccount
    {
        $externalId = (string) $remote->getId();

        $existing = LinkedAccount::query()
            ->where('social_provider_id', $provider->id)
            ->where('external_id', $externalId)
            ->first();

        if ($existing !== null && $existing->user_id !== $user->id) {
            throw AccountLinkRefusedException::ownedByAnotherUser();
        }

        $email = $remote->getEmail();

        if ($email !== null && LinkedAccount::query()->where('email', $email)->where('user_id', '!=', $user->id)->exists()) {
            throw AccountLinkRefusedException::ownedByAnotherUser();
        }

        if ($existing === null && $user->accounts()->where('social_provider_id', $provider->id)->exists()) {
            throw AccountLinkRefusedException::providerAlreadyLinked($provider->name);
        }

        $account = $existing ?? new LinkedAccount;
        $account->user()->associate($user);
        $account->provider()->associate($provider);
        $account->external_id = $externalId;
        $account->name = $remote->getNickname() ?: $remote->getName();
        $account->email = $email;
        $account->avatar_url = $remote->getAvatar();
        $account->access_token = $remote instanceof TwoUser ? $remote->token : null;
        $account->refresh_token = $remote instanceof TwoUser ? $remote->refreshToken : null;
        $account->needs_relink = false;
        $account->save();

        return $account;
    }
}
