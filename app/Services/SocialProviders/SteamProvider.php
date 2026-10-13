<?php

namespace App\Services\SocialProviders;

use App\Enums\SettingType;
use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Manager\Config;
use SocialiteProviders\Manager\OAuth2\AbstractProvider as ManagerProvider;
use SocialiteProviders\Steam\Provider;

class SteamProvider extends AbstractSocialProvider
{
    protected string $name = 'Steam';

    protected string $code = 'steam';

    protected string $socialiteProviderCode = 'steam';

    protected bool $supportsAuth = true;

    /**
     * @return array<string, object{name: string, validation?: string, encrypted?: bool, description?: string, type?: SettingType, value?: mixed}>
     */
    public function configMapping(): array
    {
        return [
            'client_secret' => (object) [
                'name' => 'API Key',
                'validation' => 'required|string',
                'encrypted' => true,
            ],
        ];
    }

    protected function getSocialiteProvider(): AbstractProvider
    {
        $host = request()->getHost();
        $config = new Config(
            '',
            $this->getSetting('client_secret'),
            $this->redirectUrl,
            [
                'allowed_hosts' => $host,
            ]
        );

        $provider = Socialite::buildProvider(Provider::class, $config->get());
        if (! $provider instanceof ManagerProvider) {
            throw new SocialProviderException('Unexpected Socialite provider');
        }

        return $provider->setConfig($config);
    }

    protected function updateAccount(LinkedAccount $account, SocialiteUser $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->name = $remoteUser->getNickname();
    }
}
