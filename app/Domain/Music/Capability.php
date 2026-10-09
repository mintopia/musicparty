<?php

namespace App\Domain\Music;

enum Capability: string
{
    case PlaylistWrite = 'playlist-write';
}
