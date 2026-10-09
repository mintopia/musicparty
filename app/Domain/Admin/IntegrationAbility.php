<?php

namespace App\Domain\Admin;

enum IntegrationAbility: string
{
    case Read = 'read';
    case Export = 'export';
}
