<?php

namespace Tests\Fixtures\Playback;

use App\Domain\Playback\Contracts\HandlesPlayerFrames;
use App\Domain\Playback\Testing\FakePlayer;

class FrameHandlingPlayer extends FakePlayer implements HandlesPlayerFrames
{
    /** @var list<array<string, mixed>> */
    public array $frames = [];

    /**
     * @param  array<string, mixed>  $frame
     */
    public ?\Closure $onFrame = null;

    public function handleFrame(array $frame): bool
    {
        if ($this->onFrame !== null) {
            ($this->onFrame)();
        }

        $this->frames[] = $frame;

        return ! isset($frame['malformed']);
    }
}
