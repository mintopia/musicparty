<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;
use RuntimeException;

class GeneratePartyCode
{
    private const int MAX_ATTEMPTS = 10;

    public function __invoke(): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $code = $this->candidate();

            if (! Party::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to generate a unique party code.');
    }

    protected function candidate(): string
    {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= chr(random_int(65, 90));
        }

        return $code;
    }
}
