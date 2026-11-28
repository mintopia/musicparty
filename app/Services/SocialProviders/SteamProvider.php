<?php

namespace App\Services\SocialProviders;

use App\Enums\SettingType;
use App\Exceptions\SocialCallbackRejectedException;
use App\Exceptions\SocialProviderException;
use App\Models\LinkedAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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

    public const NONCE_SESSION_KEY = 'steam_return_nonce';

    private ?string $returnNonce = null;

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
            $this->returnNonce === null
                ? $this->redirectUrl
                : $this->redirectUrl.'?'.http_build_query(['nonce' => $this->returnNonce]),
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

    public function redirect(): RedirectResponse
    {
        $this->returnNonce = Str::random(40);
        session()->put(self::NONCE_SESSION_KEY, $this->returnNonce);

        return parent::redirect();
    }

    public function user(?User $localUser = null)
    {
        $expected = session()->pull(self::NONCE_SESSION_KEY);
        $given = request()->query('nonce');

        if (! is_string($expected) || ! is_string($given) || ! hash_equals($expected, $given)) {
            throw new SocialCallbackRejectedException('Steam could not verify this request. Please try again.');
        }

        return parent::user($localUser);
    }

    protected function updateAccount(LinkedAccount $account, SocialiteUser $remoteUser): void
    {
        $account->avatar_url = $remoteUser->getAvatar();
        $account->name = $remoteUser->getNickname();
    }
}
