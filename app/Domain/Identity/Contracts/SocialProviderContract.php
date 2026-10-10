<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\SocialProvider;
use App\Domain\Identity\Models\User;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Provider;

interface SocialProviderContract
{
    /**
     * Create a new Social Provider instance
     */
    public function __construct(?SocialProvider $provider = null, ?string $redirectUrl = null);

    /**
     * Fetch configuration information for the provider.
     */
    /**
     * @return array<string, \stdClass>
     */
    public function configMapping(): array;

    /**
     * Create a SocialProvider object for this provider.
     */
    public function install(): SocialProvider;

    /**
     * Return a redirect to the Social Provider's login page.
     */
    public function redirect(): RedirectResponse;

    public function driver(): Provider;

    /**
     * Process the authentication result and return a local user if appropriate.
     *
     * @return mixed
     */
    public function user(?User $localUser = null);
}
