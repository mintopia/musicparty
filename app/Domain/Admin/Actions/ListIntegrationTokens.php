<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\Integration;
use Illuminate\Database\Eloquent\Collection;

class ListIntegrationTokens
{
    /**
     * @return Collection<int, Integration>
     */
    public function handle(): Collection
    {
        return Integration::query()->with('tokens')->orderByDesc('id')->get();
    }
}
