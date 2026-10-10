<?php

namespace App\Domain\Membership;

enum PartyRole: string
{
    case Host = 'host';
    case Moderator = 'moderator';
    case Vip = 'vip';
    case Guest = 'guest';
}
