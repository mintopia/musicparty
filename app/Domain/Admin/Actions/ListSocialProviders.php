<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\ProviderCatalogue;
use App\Models\SocialProvider;
use Illuminate\Support\Collection;

class ListSocialProviders
{
    public function __construct(private readonly EnsureSocialProvider $ensure) {}

    /**
     * @return Collection<int, SocialProvider>
     */
    public function handle(): Collection
    {
        return collect(ProviderCatalogue::codes())->map(fn (string $code): SocialProvider => $this->ensure->handle($code));
    }
}
