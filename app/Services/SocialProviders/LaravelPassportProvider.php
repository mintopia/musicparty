<?php

namespace App\Services\SocialProviders;

use App\Enums\SettingType;
use App\Exceptions\SocialProviderException;
use App\Models\SocialProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use SocialiteProviders\LaravelPassport\Provider;
use SocialiteProviders\Manager\Config;
use SocialiteProviders\Manager\OAuth2\AbstractProvider as ManagerProvider;

class LaravelPassportProvider extends AbstractSocialProvider
{
    protected string $name = 'Laravel Passport';

    protected string $code = 'laravelpassport';

    protected string $socialiteProviderCode = 'laravelpassport';

    protected bool $supportsAuth = true;

    protected bool $canBeRenamed = true;

    public function __construct(?SocialProvider $provider = null, ?string $redirectUrl = null)
    {
        parent::__construct($provider, $redirectUrl);
        if ($provider != null) {
            $this->name = $provider->name;
        }
    }

    protected function getSocialiteProvider(): AbstractProvider
    {
        $config = new Config(
            $this->getSetting('client_id'),
            $this->getSetting('client_secret'),
            $this->redirectUrl,
            ['host' => $this->getSetting('host')]
        );

        $provider = Socialite::buildProvider(Provider::class, $config->get());
        if (! $provider instanceof ManagerProvider) {
            throw new SocialProviderException('Unexpected Socialite provider');
        }

        return $provider->setConfig($config)->with(['prompt' => 'none']);
    }

    /**
     * @return array<string, object{name: string, validation?: string, encrypted?: bool, description?: string, type?: SettingType, value?: mixed}>
     */
    public function configMapping(): array
    {
        return array_merge(
            parent::configMapping(),
            [
                'host' => (object) [
                    'name' => 'Passport Host',
                    'validation' => 'required|string',
                ],
            ],
        );
    }
}
