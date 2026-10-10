<?php

namespace App\Domain\Music\Actions;

use App\Domain\Music\Data\HostAccountLinkData;
use App\Domain\Music\Exceptions\AccountAlreadyLinkedException;
use App\Domain\Music\Exceptions\NotHostException;
use App\Models\LinkedAccount;
use App\Models\Party;
use App\Models\SocialProvider;
use App\Models\User;

class LinkHostAccount
{
    public function __construct(private readonly AuthorisesHost $authorisesHost) {}

    /**
     * @throws NotHostException
     * @throws AccountAlreadyLinkedException
     */
    public function __invoke(User $user, Party $party, HostAccountLinkData $data, string $providerCode = 'spotify'): LinkedAccount
    {
        $this->authorisesHost->__invoke($user, $party);

        $provider = SocialProvider::query()->where('code', $providerCode)->firstOrFail();

        $taken = LinkedAccount::query()
            ->where('social_provider_id', $provider->id)
            ->where('external_id', $data->externalId)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($taken) {
            throw new AccountAlreadyLinkedException;
        }

        $account = LinkedAccount::query()
            ->where('social_provider_id', $provider->id)
            ->where('user_id', $user->id)
            ->first() ?? new LinkedAccount;

        $account->provider()->associate($provider);
        $account->user()->associate($user);
        $account->external_id = $data->externalId;
        $account->name = $data->name;
        $account->access_token = $data->accessToken;
        $account->refresh_token = $data->refreshToken ?? $account->refresh_token;
        $account->access_token_expires_at = $data->expiresIn !== null ? now()->addSeconds($data->expiresIn) : null;
        $account->needs_relink = false;
        $account->save();

        return $account;
    }
}
