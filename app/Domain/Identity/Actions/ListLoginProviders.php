<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialProvider;
use Illuminate\Support\Collection;

class ListLoginProviders
{
    /**
     * @return Collection<int, SocialProvider>
     */
    public function __invoke(): Collection
    {
        return SocialProvider::query()
            ->with('settings')
            ->where('enabled', true)
            ->where('auth_enabled', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (SocialProvider $provider): bool => $provider->isConfigured())
            ->values();
    }
}
