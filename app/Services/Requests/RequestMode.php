<?php

namespace App\Services\Requests;

enum RequestMode: string
{
    case Submit = 'submit';
    case Annotate = 'annotate';
}
