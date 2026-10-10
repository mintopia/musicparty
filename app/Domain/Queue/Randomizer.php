<?php

namespace App\Domain\Queue;

interface Randomizer
{
    /**
     * Returns an integer between $min and $max inclusive.
     */
    public function between(int $min, int $max): int;
}
