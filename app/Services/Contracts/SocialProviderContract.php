<?php

namespace App\Services\Contracts;

use App\Models\SocialProvider;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

interface SocialProviderContract
{
    /**
     * Create a new Social Provider instance
     *
     * @param SocialProvider|null $provider
     * @param string|null $redirectUrl
     */
    public function __construct(?SocialProvider $provider = null, ?string $redirectUrl = null);

    /**
     * Fetch configuration information for the provider.
     *
     * @return array
     */
    public function configMapping(): array;

    /**
     * Create a SocialProvider object for this provider.
     *
     * @return SocialProvider
     */
    public function install(): SocialProvider;

    /**
     * Return a redirect to the Social Provider's login page.
     *
     * @return RedirectResponse
     */
    public function redirect(): RedirectResponse;

    /**
     * Process the authentication result and return a local user if appropriate.
     *
     * @param User|null $localUser
     * @return mixed
     */
    public function user(?User $localUser = null);
}
