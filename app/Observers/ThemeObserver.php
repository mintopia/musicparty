<?php

namespace App\Observers;

use App\Models\Theme;
use Illuminate\Support\Str;
use function App\makePermalink;

class ThemeObserver
{
    public function saved(Theme $theme): void
    {
        if ($theme->active) {
            $others = Theme::whereActive(true)->where('id', '<>', $theme->id)->get();
            foreach ($others as $other) {
                $other->active = false;
                $other->save();
            }
        }
    }

    public function saving(Theme $theme): void
    {
        $theme->code = Str::slug($theme->name);
    }

    public function deleted(Theme $theme): void
    {
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
