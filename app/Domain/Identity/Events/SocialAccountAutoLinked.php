<?php

namespace App\Domain\Identity\Events;

use App\Domain\Identity\Models\LinkedAccount;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class SocialAccountAutoLinked
{
    use Dispatchable;

    public function __construct(
        public User $user,
        public LinkedAccount $account,
    ) {}
}
