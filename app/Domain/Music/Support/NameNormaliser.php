<?php

namespace App\Domain\Music\Support;

final class NameNormaliser
{
    public static function normalise(?string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $name)));
    }

    /**
     * @param  iterable<mixed>|null  $names
     * @return list<string>
     */
    public static function normaliseAll(?iterable $names): array
    {
        $normalised = [];

        foreach ($names ?? [] as $name) {
            $value = self::normalise(is_string($name) ? $name : null);

            if ($value !== '') {
                $normalised[$value] = $value;
            }
        }

        return array_values($normalised);
    }
}
