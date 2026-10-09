<?php

namespace App\Domain\Queue;

enum SelectionMode: string
{
    case Deterministic = 'deterministic';
    case Weighted = 'weighted';
}
