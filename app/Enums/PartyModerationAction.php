<?php

namespace App\Enums;

use Illuminate\Support\Collection;

enum PartyModerationAction: string
{
    case Reject = 'reject';
    case Flag = 'flag';

    /**
     * @return Collection<string, string>
     */
    public static function getHumanReadableActions(): Collection
    {
        return collect([
            self::Reject->value => 'Reject the request',
            self::Flag->value => 'Flag for approval',
        ]);
    }
}
