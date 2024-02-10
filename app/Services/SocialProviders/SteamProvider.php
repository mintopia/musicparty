<?php

namespace App\Services\SocialProviders;

use App\Models\LinkedAccount;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Manager\Config;
use SocialiteProviders\Steam\Provider;

class SteamProvider extends AbstractSocialProvider
{
    protected string $name = 'Steam';
    protected string $code = 'steam';
    protected string $socialiteProviderCode = 'steam';
    protected bool $supportsAuth = true;

    public function configMapping(): array
    {
        return [
            'client_secret' => (object)[
                'name' => 'API Key',
                'validation' => 'required|string',
                'encrypted' => true,
            ],
        ];
    }

    protected function getSocialiteProvider()
    {
        $host = request()->getHost();
        $config = new Config(
            null,
            $this->provider->getSetting('client_secret'),
            $this->redirectUrl,
            [
                'allowed_hosts' => $host,
            ]
        );
        return Socialite::buildProvider(Provider::class, $config->get())->setConfig($config);
    }

    protected function updateAccount(LinkedAccount $account, $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->name = $remoteUser->getNickname();
    }
}
