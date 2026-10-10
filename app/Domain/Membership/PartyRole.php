<?php

namespace App\Domain\Membership;

enum PartyRole: string
{
    case Host = 'host';
    case Moderator = 'moderator';
    case Vip = 'vip';
    case Guest = 'guest';

    public function isHost(): bool
    {
        return $this === self::Host;
    }

    public function isModerator(): bool
    {
        return $this === self::Moderator;
    }

    public function isStaff(): bool
    {
        return $this === self::Host || $this === self::Moderator;
    }

    public function isExemptFromRequestLimit(): bool
    {
        return $this === self::Host || $this === self::Vip;
    }
}
