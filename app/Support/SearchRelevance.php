<?php

namespace App\Support;

class SearchRelevance
{
    /**
     * @param  list<string>  $candidates
     */
    public static function isWeak(string $query, array $candidates): bool
    {
        $words = self::words($query);
        if ($words === [] || $candidates === []) {
            return false;
        }

        foreach ($candidates as $candidate) {
            $haystack = implode(' ', self::words($candidate));
            if (array_all($words, fn (string $word): bool => str_contains($haystack, $word))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private static function words(string $text): array
    {
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', '', mb_strtolower($text)) ?? '';

        return array_values(array_filter(preg_split('/\s+/u', trim($clean)) ?: []));
    }
}
