<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PartyFeatureDisabledException extends NotFoundHttpException
{
    public function __construct(
        public readonly string $title,
        public readonly string $ownerMessage,
        public readonly string $moderatorMessage,
        public readonly string $icon,
    ) {
        parent::__construct($title);
    }

    public static function aiReviews(): self
    {
        return new self(
            'AI reviews are off for this party',
            'Turn on the AI Request Review mod to have new Requests checked against your guidelines.',
            "The host hasn't turned this on. Moderators still approve or reject Flagged Requests by hand.",
            'ti-robot-off',
        );
    }
}
