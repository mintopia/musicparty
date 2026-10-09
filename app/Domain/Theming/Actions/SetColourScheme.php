<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Theming\ColourScheme;
use App\Models\User;

class SetColourScheme
{
    public function handle(User $user, ColourScheme $scheme): User
    {
        $user->forceFill(['colour_scheme' => $scheme])->save();

        return $user;
    }
}
