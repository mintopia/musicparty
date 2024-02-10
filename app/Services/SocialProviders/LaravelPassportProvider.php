<?php

namespace App\Services\SocialProviders;

use App\Models\LinkedAccount;
use App\Models\SocialProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\LaravelPassport\Provider;
use SocialiteProviders\Manager\Config;

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
        if($provider != null) {
            $this->name = $provider->name;
        }
    }

    protected function getSocialiteProvider()
    {
        $config = new Config(
            $this->provider->getSetting('client_id'),
            $this->provider->getSetting('client_secret'),
            $this->redirectUrl,
            ['host' =>  $this->provider->getSetting('host')]
        );
        return Socialite::buildProvider(Provider::class, $config->get())
            ->setConfig($config)->with(['prompt' => 'none']);
    }

    public function configMapping(): array
    {
        return array_merge(
            parent::configMapping(),
            [
                'host' => (object)[
                    'name' => 'Passport Host',
                    'validation' => 'required|string',
                ],
            ],
        );
    }
}
