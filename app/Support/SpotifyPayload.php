<?php

namespace App\Support;

use stdClass;

final class SpotifyPayload
{
    /**
     * @param  array<array-key, mixed>|object  $payload
     */
    public static function object(array|object $payload): stdClass
    {
        return $payload instanceof stdClass ? $payload : (object) (array) $payload;
    }
}
