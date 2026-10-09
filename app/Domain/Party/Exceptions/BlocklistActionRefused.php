<?php

namespace App\Domain\Party\Exceptions;

use RuntimeException;

class BlocklistActionRefused extends RuntimeException
{
    public const FORBIDDEN = 403;

    public const UNPROCESSABLE = 422;

    public static function notAllowed(): self
    {
        return new self('You are not allowed to manage the blocklist in this party.', self::FORBIDDEN);
    }

    public static function regexNotSupported(): self
    {
        return new self('Only name matches can use a regular expression.', self::UNPROCESSABLE);
    }

    public static function invalidRegex(): self
    {
        return new self('That regular expression is not valid.', self::UNPROCESSABLE);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
