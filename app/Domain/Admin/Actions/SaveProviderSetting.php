<?php

namespace App\Domain\Admin\Actions;

use App\Domain\Admin\Models\ProviderSetting;

readonly class SaveProviderSetting
{
    public function __invoke(ProviderSetting $setting): ProviderSetting
    {
        if ($setting->isDirty() || ! $setting->exists) {
            $setting->save();
        }

        return $setting;
    }
}
