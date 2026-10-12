<?php

namespace App\Domain\Music\Actions;

use App\Domain\Identity\Actions\UpdateLinkedAccount;
use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use App\Domain\Music\Data\HostAccountLinkData;
use App\Domain\Music\Exceptions\AccountAlreadyLinkedException;
use App\Domain\Music\Exceptions\NotHostException;

class LinkHostAccount
{
    public function __construct(private readonly AuthorisesSpotifyLinking $authorisesLinking, private readonly UpdateLinkedAccount $updateLinkedAccount) {}

    /**
     * @throws NotHostException
     * @throws AccountAlreadyLinkedException
     */
    public function __invoke(User $user, HostAccountLinkData $data, string $providerCode = 'spotify'): LinkedAccount
    {
        ($this->authorisesLinking)($user);

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

        return ($this->updateLinkedAccount)($account, [
            'external_id' => $data->externalId,
            'name' => $data->name,
            'access_token' => $data->accessToken,
            'refresh_token' => $data->refreshToken ?? $account->refresh_token,
            'access_token_expires_at' => $data->expiresIn !== null ? now()->addSeconds($data->expiresIn) : null,
            'scopes' => $data->scopes,
            'needs_relink' => false,
        ]);
    }
}
