<?php

namespace App\Domain\Playback\Contracts;

interface HandlesPlayerFrames
{
    /**
     * @param  array<string, mixed>  $frame  Native frame exactly as the player sent it
     * @return bool False when the frame is malformed and was discarded
     */
    public function handleFrame(array $frame): bool;
}
