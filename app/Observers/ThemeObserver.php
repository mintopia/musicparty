<?php

namespace App\Observers;

use App\Models\Theme;
use App\Services\ActiveTheme;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ThemeObserver
{
    public function saved(Theme $theme): void
    {
        $this->savedOrDeleted($theme);
        if ($theme->active) {
            $others = Theme::whereActive(true)->where('id', '<>', $theme->id)->get();
            foreach ($others as $other) {
                $other->active = false;
                $other->save();
            }
        }
    }

    public function savedOrDeleted(Theme $theme): void
    {
        Cache::forget(ActiveTheme::CACHE_KEY);
        Cache::forget("theme.css.{$theme->code}");
        Cache::forget("theme.css.{$theme->getOriginal('code')}");
    }

    public function saving(Theme $theme): void
    {
        $theme->code = Str::slug($theme->name);
    }

    public function deleted(Theme $theme): void
    {
        $this->savedOrDeleted($theme);
        $active = Theme::whereActive(true)->count();
        if ($active === 0) {
            $default = Theme::whereCode('default')->first();
            if ($default) {
                $default->active = true;
                $default->save();
            }
        }
    }
}
