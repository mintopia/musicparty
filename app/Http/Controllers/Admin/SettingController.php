<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SettingType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingUpdateRequest;
use App\Models\Setting;
use App\Models\SocialProvider;
use App\Models\Theme;
use App\Models\TicketProvider;

class SettingController extends Controller
{
    public function index()
    {
        $socialProviders = SocialProvider::get();
        $settings = Setting::whereHidden(false)->ordered()->get();
        $themes = Theme::get();
        return view('admin.settings.index', [
            'socialProviders' => $socialProviders,
            'themes' => $themes,
            'settings' => $settings,
        ]);
    }

    public function update(SettingUpdateRequest $request)
    {
        $settings = Setting::whereHidden(false)->get();
        foreach ($settings as $setting) {
            if ($request->has($setting->code) || $setting->type === SettingType::stBoolean) {
                $value = $request->input($setting->code);
                if ($setting->type === SettingType::stBoolean) {
                    $value = (bool)$value;
                }
                $setting->value = $value;
                $setting->save();
            }
        }
        return response()->redirectToRoute('admin.settings.index')->with('successMessage', 'Settings have been updated');
    }
}
