<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Support\Str;

class PartyCodeGenerator
{
    public const FORMAT = '/^[A-Z0-9]{3,10}$/';

    public function random(): string
    {
        return Str::upper(Str::password(4, numbers: false, symbols: false));
    }

    public function unused(): string
    {
        do {
            $code = $this->random();
        } while (Party::query()->where('code', $code)->exists());

        return $code;
    }
}
