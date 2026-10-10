<?php

namespace App\Domain\Theming;

class ContrastWarnings
{
    /**
     * @param  array{light: array<string, string>, dark: array<string, string>, font?: string}  $theme
     * @return list<array{scheme: string, pair: string, ratio: float}>
     */
    public function for(array $theme): array
    {
        $warnings = [];
        foreach (['light', 'dark'] as $scheme) {
            foreach (['background', 'surface'] as $against) {
                $text = $theme[$scheme]['text'] ?? null;
                $other = $theme[$scheme][$against] ?? null;
                if ($text === null || $other === null || ! ThemeTokens::isHex($text) || ! ThemeTokens::isHex($other)) {
                    continue;
                }
                if (! Contrast::meetsAA($text, $other)) {
                    $warnings[] = [
                        'scheme' => $scheme,
                        'pair' => 'text/'.$against,
                        'ratio' => round(Contrast::ratio($text, $other), 2),
                    ];
                }
            }
        }

        return $warnings;
    }
}
