<?php

namespace App\Domain\Theming;

class ThemeTokens
{
    public const DEFAULT_FONT = 'inter';

    public const HEX_PATTERN = '/\A#[0-9a-fA-F]{6}\z/';

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'primary' => 'Primary',
        'accent' => 'Accent',
        'danger' => 'Danger',
        'background' => 'Background',
        'surface' => 'Surface',
        'text' => 'Text',
        'muted' => 'Muted text',
        'border' => 'Border',
        'sidebar' => 'Sidebar',
        'sidebar-text' => 'Sidebar text',
        'sidebar-active' => 'Sidebar active',
        'topbar' => 'Top bar',
        'hero' => 'Hero',
        'hero-text' => 'Hero text',
    ];

    /**
     * @var list<string>
     */
    public const PARTY_KEYS = ['primary', 'accent', 'background', 'surface', 'text', 'danger'];

    public const DEFAULT_TV_LAYOUT = 'default';

    /**
     * @var array<string, string>
     */
    public const TV_LAYOUTS = [
        'default' => 'Default',
        'compact' => 'Compact queue',
        'fullscreen-art' => 'Full-screen artwork',
        'queue-focus' => 'Queue focus',
    ];

    /**
     * @var array<string, array{label: string, stack: string}>
     */
    public const FONTS = [
        'inter' => ['label' => 'Inter', 'stack' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"],
        'system' => ['label' => 'System UI', 'stack' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif"],
        'serif' => ['label' => 'Serif', 'stack' => "Georgia, 'Times New Roman', Times, serif"],
        'mono' => ['label' => 'Monospace', 'stack' => "ui-monospace, SFMono-Regular, Menlo, Consolas, 'Liberation Mono', monospace"],
    ];

    /**
     * @var array<string, string>
     */
    private const array LIGHT = [
        'primary' => '#066fd1',
        'accent' => '#2fb344',
        'danger' => '#d63939',
        'background' => '#f6f8fb',
        'surface' => '#ffffff',
        'text' => '#182433',
        'muted' => '#667382',
        'border' => '#e6e7e9',
        'sidebar' => '#182433',
        'sidebar-text' => '#9ba6b4',
        'sidebar-active' => '#ffffff',
        'topbar' => '#ffffff',
        'hero' => '#0b0f14',
        'hero-text' => '#ffffff',
    ];

    /**
     * @var array<string, string>
     */
    private const array DARK = [
        'primary' => '#066fd1',
        'accent' => '#2fb344',
        'danger' => '#d63939',
        'background' => '#151f2c',
        'surface' => '#182433',
        'text' => '#dce1e7',
        'muted' => '#9ba6b4',
        'border' => '#243049',
        'sidebar' => '#182433',
        'sidebar-text' => '#9ba6b4',
        'sidebar-active' => '#ffffff',
        'topbar' => '#182433',
        'hero' => '#0b0f14',
        'hero-text' => '#ffffff',
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    /**
     * @return list<string>
     */
    public static function fontKeys(): array
    {
        return array_keys(self::FONTS);
    }

    /**
     * @return list<string>
     */
    public static function partyKeys(): array
    {
        return self::PARTY_KEYS;
    }

    /**
     * @return list<string>
     */
    public static function tvLayoutKeys(): array
    {
        return array_keys(self::TV_LAYOUTS);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function tvLayoutList(): array
    {
        $list = [];
        foreach (self::TV_LAYOUTS as $value => $label) {
            $list[] = ['value' => $value, 'label' => $label];
        }

        return $list;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function partyTokenList(): array
    {
        $list = [];
        foreach (self::PARTY_KEYS as $key) {
            $list[] = ['key' => $key, 'label' => self::LABELS[$key]];
        }

        return $list;
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function tokenList(): array
    {
        $list = [];
        foreach (self::LABELS as $key => $label) {
            $list[] = ['key' => $key, 'label' => $label];
        }

        return $list;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function fontList(): array
    {
        $list = [];
        foreach (self::FONTS as $value => $font) {
            $list[] = ['value' => $value, 'label' => $font['label']];
        }

        return $list;
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: string}
     */
    public static function defaults(): array
    {
        return ['light' => self::LIGHT, 'dark' => self::DARK, 'font' => self::DEFAULT_FONT];
    }

    public static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match(self::HEX_PATTERN, $value) === 1;
    }
}
