<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Theming\ThemeTokens;

class RenderThemeCss
{
    public function __construct(private readonly GetInstanceTheme $getInstanceTheme) {}

    public function handle(): string
    {
        $theme = $this->getInstanceTheme->handle();
        $light = $this->declarations($theme['light']);
        $dark = $this->declarations($theme['dark']);
        $font = ThemeTokens::FONTS[$theme['font']]['stack'] ?? ThemeTokens::FONTS[ThemeTokens::DEFAULT_FONT]['stack'];

        return ':root{'.$light.'--font-sans:'.$font.';}.dark{'.$dark.'}';
    }

    /**
     * @param  array<string, string>  $values
     */
    private function declarations(array $values): string
    {
        $css = '';
        foreach (ThemeTokens::keys() as $key) {
            $value = $values[$key] ?? null;
            if (ThemeTokens::isHex($value)) {
                $css .= '--color-'.$key.':'.$value.';';
            }
        }

        return $css;
    }
}
