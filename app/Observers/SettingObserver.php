<?php

namespace App\Observers;

use App\Domain\Admin\Models\Setting;

class SettingObserver
{
    public function saved(Setting $setting): void
    {
        $setting->clearCache();
    }

    public function deleted(Setting $setting): void
    {
        $setting->clearCache();
    }
}
