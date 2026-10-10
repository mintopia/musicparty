<?php

namespace App\Domain\Party\Exceptions;

use RuntimeException;

class MembershipActionRefused extends RuntimeException
{
    public const FORBIDDEN = 403;

    public const UNPROCESSABLE = 422;

    public static function notAllowed(): self
    {
        return new self('You are not allowed to do that in this party.', self::FORBIDDEN);
    }

    public static function onlyHostChangesRoles(): self
    {
        return new self('Only the Host can change roles.', self::FORBIDDEN);
    }

    public static function hostRoleLocked(): self
    {
        return new self("The Host's role cannot be changed.", self::UNPROCESSABLE);
    }

    public static function hostRoleNotAssignable(): self
    {
        return new self('The Host role cannot be given to another member.', self::UNPROCESSABLE);
    }

    public static function cannotBanHost(): self
    {
        return new self('The Host cannot be banned.', self::FORBIDDEN);
    }

    public static function cannotActOnModerator(): self
    {
        return new self('Only the Host can ban or unban a Moderator.', self::FORBIDDEN);
    }

    public static function cannotBanSelf(): self
    {
        return new self('You cannot ban yourself.', self::UNPROCESSABLE);
    }

    public function status(): int
    {
        return $this->getCode();
    }
}
