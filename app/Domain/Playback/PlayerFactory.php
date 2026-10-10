<?php

namespace App\Domain\Playback;

use App\Domain\Playback\Contracts\Player;
use Closure;
use InvalidArgumentException;

class PlayerFactory
{
    /** @var array<string, Closure(): Player> */
    private array $resolvers = [];

    /**
     * @param  Closure(): Player  $resolver
     */
    public function extend(string $kind, Closure $resolver): void
    {
        $this->resolvers[$kind] = $resolver;
    }

    public function make(string $kind): Player
    {
        if (isset($this->resolvers[$kind])) {
            return ($this->resolvers[$kind])();
        }

        $class = config("musicparty.players.{$kind}.class");

        if (! is_string($class)) {
            throw new InvalidArgumentException("Unknown Player kind [{$kind}].");
        }

        /** @var Player */
        return app($class);
    }
}
