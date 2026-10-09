<?php

namespace App\Domain\Queue\Exceptions;

use RuntimeException;

class RequestRefusedException extends RuntimeException
{
    public const NOT_A_MEMBER = 403;

    public const BANNED = 403;

    public const PARTY_NOT_LIVE = 422;

    public const REQUESTS_DISABLED = 422;

    public const UNKNOWN_TRACK = 422;

    public const PROVIDER_UNAVAILABLE = 503;

    public static function notAMember(): self
    {
        return new self('Join the party to request tracks.', self::NOT_A_MEMBER);
    }

    public static function banned(): self
    {
        return new self('You have been banned from requesting tracks in this party.', self::BANNED);
    }

    public static function notAMemberToRate(): self
    {
        return new self('Join the party to rate tracks.', self::NOT_A_MEMBER);
    }

    public static function bannedFromRating(): self
    {
        return new self('You have been banned from rating tracks in this party.', self::BANNED);
    }

    public static function partyNotLive(): self
    {
        return new self('This party is not live, so requests are closed.', self::PARTY_NOT_LIVE);
    }

    public static function requestsDisabled(): self
    {
        return new self('Requests are disabled for this party.', self::REQUESTS_DISABLED);
    }

    public function status(): int
    {
        $code = $this->getCode();

        return $code >= 400 && $code <= 599 ? $code : 500;
    }

    public static function unknownTrack(): self
    {
        return new self('That track could not be found.', self::UNKNOWN_TRACK);
    }

    public static function providerUnavailable(): self
    {
        return new self('The music provider is unavailable right now. Try again shortly.', self::PROVIDER_UNAVAILABLE);
    }
}
