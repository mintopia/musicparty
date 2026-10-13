<?php

namespace App\Services;

use App\Models\Theme;
use Illuminate\Support\Facades\Cache;

class ActiveTheme
{
    public const CACHE_KEY = 'theme.active';

    private bool $resolved = false;

    private ?Theme $theme = null;

    public function current(): ?Theme
    {
        if (! $this->resolved) {
            $cached = Cache::rememberForever(self::CACHE_KEY, fn (): Theme|false => Theme::whereActive(true)->first() ?? false);
            $this->theme = $cached instanceof Theme ? $cached : null;
            $this->resolved = true;
        }

        return $this->theme;
    }

    public function darkMode(): bool
    {
        return (bool) $this->current()?->dark_mode;
    }

    /**
     * @return array{tokens: array<string, string>, stylesheet: string, darkMode: bool}|null
     */
    public function shared(): ?array
    {
        $theme = $this->current();
        if ($theme === null) {
            return null;
        }

        return [
            'tokens' => $theme->validTokens(),
            'stylesheet' => $theme->stylesheetUrl(),
            'darkMode' => $theme->dark_mode,
        ];
    }
}
