<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialProvider;

readonly class CreateSocialProvider
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __invoke(array $attributes): SocialProvider
    {
        $provider = new SocialProvider;
        $provider->forceFill($attributes)->save();

        return $provider;
    }
}
