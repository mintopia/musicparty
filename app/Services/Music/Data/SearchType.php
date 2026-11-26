<?php

namespace App\Services\Music\Data;

enum SearchType: string
{
    case Track = 'track';
    case Album = 'album';
    case Artist = 'artist';
}
