<?php

namespace App\Domain\Queue\Testing;

use App\Domain\Queue\Randomizer;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer as NativeRandomizer;

class SeededRandomizer implements Randomizer
{
    private readonly NativeRandomizer $native;

    public function __construct(int $seed)
    {
        $this->native = new NativeRandomizer(new Xoshiro256StarStar(hash('sha256', (string) $seed, true)));
    }

    public function between(int $min, int $max): int
    {
        return $this->native->getInt($min, $max);
    }
}
