<?php

use App\Models\SocialProvider;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\User as SocialiteUser;

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

    $driver = new class($remote)
    {
        public function __construct(private SocialiteUser $remote) {}

        public function user(): SocialiteUser
        {
            return $this->remote;
        }

        public function scopes(): static
        {
            return $this;
        }

        public function setConfig(): static
        {
            return $this;
        }

        public function with(): static
        {
            return $this;
        }
    };

    $manager = new class($driver) extends SocialiteManager
    {
        public function __construct(private object $driverDouble) {}

        public function buildProvider($provider, $config)
        {
            return $this->driverDouble;
        }
    };

    app()->instance(Factory::class, $manager);
    Socialite::clearResolvedInstances();
}
