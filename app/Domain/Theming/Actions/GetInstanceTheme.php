<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Theming\ThemeTokens;
use App\Models\InstanceTheme;

class GetInstanceTheme
{
    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: string}
     */
    public function handle(): array
    {
        $theme = ThemeTokens::defaults();
        $stored = InstanceTheme::query()->first()?->tokens;
        if (! is_array($stored)) {
            return $theme;
        }

        foreach (['light', 'dark'] as $scheme) {
            $values = $stored[$scheme] ?? null;
            if (! is_array($values)) {
                continue;
            }
            foreach (ThemeTokens::keys() as $key) {
                if (ThemeTokens::isHex($values[$key] ?? null)) {
                    $theme[$scheme][$key] = $values[$key];
                }
            }
        }

        $font = $stored['font'] ?? null;
        if (is_string($font) && array_key_exists($font, ThemeTokens::FONTS)) {
            $theme['font'] = $font;
        }

        return $theme;
    }
}
