<?php

namespace App\Domain\Queue;

class SystemRandomizer implements Randomizer
{
    public function between(int $min, int $max): int
    {
        return random_int($min, $max);
    }
}
