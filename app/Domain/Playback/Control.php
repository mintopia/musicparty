<?php

namespace App\Domain\Playback;

enum Control: string
{
    case Play = 'play';
    case Pause = 'pause';
    case Skip = 'skip';
    case Previous = 'previous';
    case Seek = 'seek';
    case Volume = 'volume';
}
