<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Identity\Actions\SetUserColourScheme;
use App\Domain\Identity\Models\User;
use App\Domain\Theming\ColourScheme;

class SetColourScheme
{
    public function __construct(private readonly SetUserColourScheme $setUserColourScheme) {}

    public function handle(User $user, ColourScheme $scheme): User
    {
        return ($this->setUserColourScheme)($user, $scheme);
    }
}
