<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Exceptions\LoginRefusedException;
use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Two\User as TwoUser;

class ResolveSocialUser
{
    /**
     * @throws LoginRefusedException
     */
    public function __invoke(SocialProvider $provider, SocialiteUser $remote): User
    {
        if (! $provider->isAvailableForLogin()) {
            throw LoginRefusedException::providerUnavailable($provider->code);
        }

        try {
            return $this->resolve($provider, $remote);
        } catch (UniqueConstraintViolationException) {
            return $this->resolve($provider, $remote);
        }
    }

    private function resolve(SocialProvider $provider, SocialiteUser $remote): User
    {
        $account = LinkedAccount::query()
            ->where('social_provider_id', $provider->id)
            ->where('external_id', (string) $remote->getId())
            ->first();

        return DB::transaction(function () use ($provider, $remote, $account): User {
            $user = $account === null ? null : User::query()->find($account->user_id);

            if ($user?->suspended) {
                throw LoginRefusedException::suspended();
            }

            if ($user === null) {
                $user = new User;
                $user->nickname = $remote->getNickname() ?: ($remote->getName() ?: 'Player');
                $user->first_login = true;
                $user->save();
            }

            $account ??= new LinkedAccount;
            $account->user()->associate($user);
            $account->provider()->associate($provider);
            $account->external_id = (string) $remote->getId();
            $account->name = $remote->getNickname() ?: $remote->getName();
            $account->email = $remote->getEmail();
            $account->avatar_url = $remote->getAvatar();
            $account->access_token = $remote instanceof TwoUser ? $remote->token : null;
            $account->refresh_token = $remote instanceof TwoUser ? $remote->refreshToken : null;
            $account->save();

            $user->last_login = now();
            $user->save();

            return $user;
        });
    }
}
