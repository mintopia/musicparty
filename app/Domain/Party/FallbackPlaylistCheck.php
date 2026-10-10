<?php

namespace App\Domain\Party;

final readonly class FallbackPlaylistCheck
{
    public function __construct(public int $playable, public int $required) {}

    public function passes(): bool
    {
        return $this->playable >= $this->required;
    }

    public function message(): string
    {
        return "The Fallback Playlist has only {$this->playable} of {$this->required} required playable Tracks.";
    }
}
