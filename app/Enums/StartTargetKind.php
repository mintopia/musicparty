<?php

namespace App\Enums;

enum StartTargetKind: string
{
    case Resume = 'resume';
    case Next = 'next';
    case Select = 'select';
}
