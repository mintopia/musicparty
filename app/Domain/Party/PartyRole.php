<?php

namespace App\Domain\Party;

enum PartyRole: string
{
    case Host = 'host';
    case Moderator = 'moderator';
    case Vip = 'vip';
    case Guest = 'guest';
}
