<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Theming\ColourScheme;

class SetColourScheme
{
    public function handle(User $user, ColourScheme $scheme): User
    {
        $user->forceFill(['colour_scheme' => $scheme])->save();

        return $user;
    }
}
