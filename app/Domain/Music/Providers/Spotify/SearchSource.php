<?php

namespace App\Domain\Music\Providers\Spotify;

use App\Domain\Identity\Models\LinkedAccount;

final readonly class SearchSource
{
    public function __construct(public ?string $clientId, public ?LinkedAccount $account = null) {}
}
