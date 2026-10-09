<?php

namespace App\Domain\Admin;

enum AdminRole: string
{
    case Admin = 'admin';
    case CreateParty = 'create-party';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::CreateParty => 'Party creator',
        };
    }
}
