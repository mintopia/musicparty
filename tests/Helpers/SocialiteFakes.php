<?php

use App\Models\SocialProvider;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\User as SocialiteUser;
use SocialiteProviders\Manager\Contracts\ConfigInterface;
use SocialiteProviders\Manager\OAuth2\AbstractProvider as ManagerProvider;

function providerFor(string $class, string $code): SocialProvider
{
    return SocialProvider::factory()->create(['code' => $code, 'provider_class' => $class]);
}

function fakeRemoteUser(string $id, ?string $email, array $raw = []): void
{
    $remote = (new SocialiteUser)->setRaw($raw)->map([
        'id' => $id,
        'nickname' => 'nick'.$id,
        'name' => 'name'.$id,
        'email' => $email,
        'avatar' => null,
    ]);
    $remote->token = 'token';
    $remote->refreshToken = 'refresh';

    $driver = new class($remote) extends ManagerProvider
    {
        public function __construct(private SocialiteUser $remote) {}

        public function user(): SocialiteUser
        {
            return $this->remote;
        }

        public function scopes($scopes): static
        {
            return $this;
        }

        public function setConfig(ConfigInterface $config): static
        {
            return $this;
        }

        public function with(array $parameters): static
        {
            return $this;
        }

        protected function getAuthUrl($state): string
        {
            return '';
        }

        protected function getTokenUrl(): string
        {
            return '';
        }

        protected function getUserByToken($token): array
        {
            return [];
        }

        protected function mapUserToObject(array $user): SocialiteUser
        {
            return $this->remote;
        }
    };

    $manager = new class($driver) extends SocialiteManager
    {
        public function __construct(private ManagerProvider $driverDouble) {}

        public function buildProvider($provider, $config)
        {
            return $this->driverDouble;
        }
    };

    app()->instance(Factory::class, $manager);
    Socialite::clearResolvedInstances();
}
