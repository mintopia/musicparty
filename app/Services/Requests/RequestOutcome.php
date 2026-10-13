<?php

namespace App\Services\Requests;

enum RequestOutcome: string
{
    case Allow = 'allow';
    case Flag = 'flag';
    case Reject = 'reject';
}
