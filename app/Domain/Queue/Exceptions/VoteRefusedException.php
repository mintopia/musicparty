<?php

namespace App\Domain\Queue\Exceptions;

use Carbon\CarbonInterface;

class VoteRefusedException extends RequestRefusedException
{
    public const NOT_VOTABLE = 409;

    public const UP_NEXT_LOCKED = 409;

    public const DOWNVOTES_DISABLED = 422;

    public const DOWNVOTE_CAP_REACHED = 429;

    public function __construct(string $message, int $code, public readonly ?CarbonInterface $retryAt = null)
    {
        parent::__construct($message, $code);
    }

    public static function notVotable(): self
    {
        return new self('Only requests waiting in the queue can be voted on.', self::NOT_VOTABLE);
    }

    public static function upNextLocked(): self
    {
        return new self('This request is up next and is locked, so votes can no longer change.', self::UP_NEXT_LOCKED);
    }

    public static function downvotesDisabled(): self
    {
        return new self('Downvotes are disabled for this party.', self::DOWNVOTES_DISABLED);
    }

    public static function downvoteCapReached(?CarbonInterface $retryAt): self
    {
        $message = $retryAt === null
            ? 'You cannot downvote in this party.'
            : 'You have used your downvotes for this hour. You can downvote again at '.$retryAt->utc()->format('H:i').' UTC.';

        return new self($message, self::DOWNVOTE_CAP_REACHED, $retryAt);
    }
}
