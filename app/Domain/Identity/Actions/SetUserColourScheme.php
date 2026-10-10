<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\User;
use BackedEnum;

readonly class SetUserColourScheme
{
    public function __invoke(User $user, BackedEnum $scheme): User
    {
        $user->forceFill(['colour_scheme' => $scheme])->save();

        return $user;
    }
}
