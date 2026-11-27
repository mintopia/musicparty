<?php

namespace App\Support;

class SearchTokens
{
    /**
     * @param  list<string>  $allowedKeys
     * @return array{text: string, tokens: array<string, list<string>>}
     */
    public static function parse(string $query, array $allowedKeys): array
    {
        $allowed = array_map(strtolower(...), $allowedKeys);
        $words = [];
        $tokens = [];

        preg_match_all('/(?:[^\s"]|"[^"]*"?)+/', $query, $matches);
        foreach ($matches[0] as $word) {
            if (preg_match('/^([A-Za-z][\w-]*):(.+)$/s', $word, $parts) === 1) {
                $key = strtolower($parts[1]);
                $value = preg_replace('/"([^"]*)"?/', '$1', $parts[2]) ?? '';
                if (in_array($key, $allowed, true) && $value !== '') {
                    if (! in_array($value, $tokens[$key] ?? [], true)) {
                        $tokens[$key][] = $value;
                    }

                    continue;
                }
            }
            $words[] = $word;
        }

        return ['text' => implode(' ', $words), 'tokens' => $tokens];
    }

    /**
     * @param  array{text: string, tokens: array<string, list<string>>}  $search
     */
    public static function serialize(array $search): string
    {
        $parts = $search['text'] === '' ? [] : [$search['text']];
        foreach ($search['tokens'] as $key => $values) {
            foreach ($values as $value) {
                $quoted = preg_match('/[\s"]/', $value) === 1 ? '"'.str_replace('"', '', $value).'"' : $value;
                $parts[] = "{$key}:{$quoted}";
            }
        }

        return implode(' ', $parts);
    }

    /**
     * @return list<string>
     */
    public static function words(string $text): array
    {
        preg_match_all('/(?:[^\s"]|"[^"]*"?)+/', $text, $matches);

        return array_values(array_filter(array_map(fn (string $word): string => str_replace('"', '', $word), $matches[0]), fn (string $word): bool => $word !== ''));
    }
}
