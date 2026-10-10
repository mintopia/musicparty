<?php

namespace App\Domain\Theming\Actions;

use App\Domain\Admin\SiteSettings;
use App\Domain\Theming\ThemeTokens;
use App\Models\Party;
use Illuminate\Support\Facades\Storage;

class GetPartyTheme
{
    public function __construct(private readonly GetInstanceTheme $getInstanceTheme, private readonly SiteSettings $site) {}

    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: string, logo_url: ?string, logo_dark_url: ?string, background_url: ?string, tv_layout: string, overrides: array{light: array<string, string>, dark: array<string, string>, font: ?string}}
     */
    public function handle(Party $party): array
    {
        $theme = $this->getInstanceTheme->handle();
        $overrides = $this->overrides($party);

        foreach (['light', 'dark'] as $scheme) {
            $theme[$scheme] = [...$theme[$scheme], ...$overrides[$scheme]];
        }
        if ($overrides['font'] !== null) {
            $theme['font'] = $overrides['font'];
        }

        $logo = $this->partyUrl($party->theme_logo_path);
        $layout = array_key_exists($party->tv_layout, ThemeTokens::TV_LAYOUTS)
            ? $party->tv_layout
            : ThemeTokens::DEFAULT_TV_LAYOUT;

        return [
            ...$theme,
            'logo_url' => $logo ?? $this->site->fileUrl('logo_light'),
            'logo_dark_url' => $logo ?? $this->site->fileUrl('logo_dark'),
            'background_url' => $this->partyUrl($party->theme_background_path),
            'tv_layout' => $layout,
            'overrides' => $overrides,
        ];
    }

    /**
     * @return array{light: array<string, string>, dark: array<string, string>, font: ?string}
     */
    public function overrides(Party $party): array
    {
        $stored = is_array($party->theme) ? $party->theme : [];
        $overrides = ['light' => [], 'dark' => [], 'font' => null];

        foreach (['light', 'dark'] as $scheme) {
            $values = $stored[$scheme] ?? null;
            if (! is_array($values)) {
                continue;
            }
            foreach (ThemeTokens::PARTY_KEYS as $key) {
                if (ThemeTokens::isHex($values[$key] ?? null)) {
                    $overrides[$scheme][$key] = $values[$key];
                }
            }
        }

        $font = $stored['font'] ?? null;
        if (is_string($font) && array_key_exists($font, ThemeTokens::FONTS)) {
            $overrides['font'] = $font;
        }

        return $overrides;
    }

    private function partyUrl(?string $path): ?string
    {
        return is_string($path) && $path !== '' ? Storage::disk(SiteSettings::disk())->url($path) : null;
    }
}
