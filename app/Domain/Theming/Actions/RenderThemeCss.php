<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Theming\ThemeTokens;

class RenderThemeCss
{
    public function __construct(private readonly GetInstanceTheme $getInstanceTheme) {}

    public function handle(): string
    {
        return $this->fromTheme($this->getInstanceTheme->handle());
    }

    /**
     * @param  array{light: array<string, string>, dark: array<string, string>, font: string}  $theme
     */
    public function fromTheme(array $theme): string
    {
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
