<?php

namespace App\Domain\Music\Support;

final class PlaylistReference
{
    private const string ID_PATTERN = '[A-Za-z0-9]{10,64}';

    /**
     * Extracts a provider playlist id from a pasted URL, URI or bare id; null when unrecognisable.
     */
    public static function parse(?string $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        $patterns = [
            '#^spotify:playlist:('.self::ID_PATTERN.')$#',
            '#^https?://[^/\s]+/(?:intl-[a-z-]+/)?playlist/('.self::ID_PATTERN.')/?(?:[?\#].*)?$#i',
            '#^('.self::ID_PATTERN.')$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }
}
