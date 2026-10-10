<?php

namespace App\Domain\Admin\Actions;

use App\Models\IntegrationToken;
use Illuminate\Database\Eloquent\Collection;

class ListIntegrationTokens
{
    /**
     * @return Collection<int, IntegrationToken>
     */
    public function handle(): Collection
    {
        return IntegrationToken::query()->orderByDesc('id')->get();
    }
}
