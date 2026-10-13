<?php

namespace App\Services\Music\Data;

enum PlaybackConnectionStatus: string
{
    case Connected = 'connected';
    case LoggedOut = 'logged_out';
    case Error = 'error';
}
